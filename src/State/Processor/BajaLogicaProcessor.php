<?php

/**
 * Processor de API Platform: marca la entidad como eliminada (fechaBaja = now) sin borrar el registro.
 */

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

class BajaLogicaProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!method_exists($data, 'setFechaBaja')) {
            throw new \LogicException(sprintf('La entidad %s no tiene el método setFechaBaja().', get_class($data)));
        }

        $data->setFechaBaja(new \DateTime('now', new \DateTimeZone('America/Argentina/Cordoba')));

        $this->em->flush();

        return $data;
    }
}

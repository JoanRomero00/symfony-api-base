<?php

/**
 * Processor de API Platform: revierte la baja lógica limpiando el campo fechaBaja.
 */

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

class ReactivarProcessor implements ProcessorInterface
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

        $data->setFechaBaja(null);

        $this->em->flush();

        return $data;
    }
}

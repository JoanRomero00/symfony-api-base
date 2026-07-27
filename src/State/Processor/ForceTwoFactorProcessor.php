<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ForceTwoFactorProcessor implements ProcessorInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Usuario
    {
        if (!$data instanceof Usuario) {
            throw new \LogicException('No se pudo resolver el usuario.');
        }

        if (null !== $data->getFechaBaja()) {
            throw new ConflictHttpException('No se puede forzar el segundo factor de un usuario dado de baja. Debe reactivarlo previamente.');
        }

        $data->setTrustedVersion(($data->getTrustedVersion() ?? 0) + 1);
        $this->entityManager->flush();

        return $data;
    }
}

<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;

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

        $data->setTrustedVersion(($data->getTrustedVersion() ?? 0) + 1);
        $this->entityManager->flush();

        return $data;
    }
}

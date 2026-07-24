<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\UsuarioRepository;

final class ForceTwoFactorAllProcessor implements ProcessorInterface
{
    public function __construct(private readonly UsuarioRepository $usuarioRepository)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $this->usuarioRepository->incrementTrustedVersionForAll();
    }
}

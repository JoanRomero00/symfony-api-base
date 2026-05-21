<?php

/**
 * Processor de API Platform: hashea el password de Usuario antes de persistir en POST y PATCH.
 */

namespace App\State\Processor;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Usuario;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Decora el PersistProcessor nativo para hashear el password antes de persistir.
 * Aplicar en operaciones POST/PATCH de Usuario donde el cliente puede enviar password en texto plano.
 */
final class UsuarioPasswordHasherProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private ProcessorInterface $persistProcessor,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Usuario && $data->getPassword()) {
            $previous = $context['previous_data'] ?? null;
            // POST: no hay previous_data → hashear. PATCH: sólo si cambió respecto al hash almacenado.
            if (!$previous instanceof Usuario || $previous->getPassword() !== $data->getPassword()) {
                $data->setPassword($this->hasher->hashPassword($data, $data->getPassword()));
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

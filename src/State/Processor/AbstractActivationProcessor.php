<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Contract\ActivatableInterface;
use App\Enum\EstadoRegistro;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<ActivatableInterface, ActivatableInterface>
 */
abstract class AbstractActivationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    final public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ActivatableInterface {
        if (!$data instanceof ActivatableInterface) {
            throw new \LogicException(sprintf('La entidad %s debe implementar %s.', get_debug_type($data), ActivatableInterface::class));
        }

        $targetState = $this->targetState()->value;

        if ($data->getEstado() !== $targetState) {
            $data->setEstado($targetState);
            $this->entityManager->flush();
        }

        return $data;
    }

    abstract protected function targetState(): EstadoRegistro;
}

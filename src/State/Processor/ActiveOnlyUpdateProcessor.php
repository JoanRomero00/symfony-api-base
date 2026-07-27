<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Contract\ActivatableInterface;
use App\Enum\EstadoRegistro;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<ActivatableInterface, ActivatableInterface>
 */
final class ActiveOnlyUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ActivatableInterface {
        if (!$data instanceof ActivatableInterface) {
            throw new \LogicException(sprintf('La entidad %s debe implementar %s.', get_debug_type($data), ActivatableInterface::class));
        }

        if (EstadoRegistro::INACTIVO->value === $data->getEstado()) {
            throw new ConflictHttpException('No se puede editar un registro dado de baja. Debe activarlo previamente.');
        }

        /** @var ActivatableInterface $result */
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        return $result;
    }
}

<?php

namespace App\State\Processor;

use App\Enum\EstadoRegistro;

final class DeactivateProcessor extends AbstractActivationProcessor
{
    protected function targetState(): EstadoRegistro
    {
        return EstadoRegistro::INACTIVO;
    }
}

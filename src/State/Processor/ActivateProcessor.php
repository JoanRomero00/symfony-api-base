<?php

namespace App\State\Processor;

use App\Enum\EstadoRegistro;

final class ActivateProcessor extends AbstractActivationProcessor
{
    protected function targetState(): EstadoRegistro
    {
        return EstadoRegistro::ACTIVO;
    }
}

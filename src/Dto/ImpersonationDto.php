<?php

/**
 * DTO de salida del sistema de suplantación: contiene el token JWT generado.
 */

namespace App\Dto;

class ImpersonationDto
{
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }
}

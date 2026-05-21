<?php

/**
 * DTO de entrada para los parámetros de consulta del reporte de actividad de usuarios.
 */

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ReporteActividadQueryDto
{
    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $fechaDesde = null;

    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $fechaHasta = null;

    public ?bool $exportar = false;
}

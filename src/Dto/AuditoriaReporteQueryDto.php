<?php

/**
 * DTO de entrada para los parámetros de consulta del reporte de auditoría.
 */

namespace App\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

class AuditoriaReporteQueryDto
{
    #[Assert\NotBlank]
    public ?string $entidad = null;

    #[Assert\Positive]
    public ?int $registroEntidadId = null;

    #[Assert\Positive]
    public ?int $usuarioId = null;

    #[ApiProperty(description: 'insert, update, delete')]
    #[Assert\Choice(choices: ['insert', 'update', 'delete'], message: 'Tipo de operación inválido. Valores permitidos: insert, update, delete.')]
    public ?string $tipoOperacion = null;

    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $fechaDesde = null;

    #[Assert\NotBlank]
    #[Assert\Date]
    public ?string $fechaHasta = null;

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(value: 1000, message: 'El límite máximo de resultados es 1000.')]
    public ?int $limiteResultados = 100;

    public ?bool $incluirModificaciones = false;
}

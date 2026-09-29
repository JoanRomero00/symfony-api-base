<?php

/**
 * Resultado de una acción individual o masiva de gestión de auditoría.
 */

namespace App\Dto;

readonly class AuditoriaActionResultDto
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?int $count = null,
    ) {
    }
}

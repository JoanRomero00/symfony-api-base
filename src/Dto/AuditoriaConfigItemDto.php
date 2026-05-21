<?php

/**
 * DTO de salida que representa el estado de auditoría de una tabla de la base de datos.
 */

namespace App\Dto;

use ApiPlatform\Metadata\ApiProperty;

readonly class AuditoriaConfigItemDto
{
    public function __construct(
        public int $id,

        #[ApiProperty(required: true)]
        public string $entidad,
        #[ApiProperty(required: true)]
        public string $tableName,

        public int $cntRegister,
        public string $size,
        public bool $isAuditable,
        public string $estado,
        public bool $isAudited,
        public bool $existTableAudit,
        public bool $existTriggerAudit,
        public int $cntRegisterAudit,
        public string $sizeAudit,
        public ?string $first = null,
        public ?string $last = null,
    ) {
    }
}

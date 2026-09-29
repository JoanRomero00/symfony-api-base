<?php

namespace App\Dto;

final readonly class AuditoriaEventosResponseDto
{
    /**
     * @param list<AuditoriaEventoDto> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $limite,
    ) {
    }
}

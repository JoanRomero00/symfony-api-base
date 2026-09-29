<?php

namespace App\Dto;

final readonly class AuditoriaEventoDto
{
    /**
     * @param array<string, mixed>|null $datosAnteriores
     * @param array<string, mixed>|null $datosNuevos
     * @param array<string, mixed>      $diferencias
     */
    public function __construct(
        public int $id,
        public string $fecha,
        public ?int $usuarioId,
        public ?string $usuario,
        public int $registroId,
        public string $operacion,
        public string $operacionCodigo,
        public ?array $datosAnteriores,
        public ?array $datosNuevos,
        public array $diferencias,
    ) {
    }
}

<?php

namespace App\Contract;

interface ActivatableInterface
{
    public function getEstado(): ?string;

    public function setEstado(string $estado): static;
}

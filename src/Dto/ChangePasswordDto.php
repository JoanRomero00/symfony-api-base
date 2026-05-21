<?php

/**
 * DTO de entrada para el endpoint de cambio de password del usuario autenticado.
 */

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordDto
{
    #[Assert\NotBlank(message: 'La contraseña actual es obligatoria')]
    public string $oldPassword;

    #[Assert\NotBlank(message: 'La nueva contraseña es obligatoria')]
    #[Assert\Length(
        min: 8,
        max: 100,
        minMessage: 'La contraseña debe tener al menos {{ limit }} caracteres'
    )]
    #[Assert\NotEqualTo(propertyPath: 'oldPassword', message: 'La nueva contraseña no puede ser igual a la actual.')]
    public string $newPassword;

    #[Assert\NotBlank]
    #[Assert\Expression(
        expression: 'this.newPassword === this.confirmPassword',
        message: 'Las contraseñas no coinciden'
    )]
    public string $confirmPassword;
}

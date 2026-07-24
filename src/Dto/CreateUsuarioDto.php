<?php

namespace App\Dto;

use App\Entity\Usuario;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateUsuarioDto
{
    #[Assert\NotBlank(message: 'Ingrese el nombre de usuario.')]
    #[Assert\Length(max: 180, maxMessage: 'El nombre de usuario no puede superar los {{ limit }} caracteres.')]
    public ?string $username = null;

    #[Assert\NotBlank(message: 'Ingrese el apellido.')]
    #[Assert\Length(max: 50, maxMessage: 'El apellido no puede superar los {{ limit }} caracteres.')]
    public ?string $apellido = null;

    #[Assert\Length(max: 50, maxMessage: 'El nombre no puede superar los {{ limit }} caracteres.')]
    public ?string $nombre = null;

    #[Assert\NotBlank(message: 'Ingrese el correo electrónico.')]
    #[Assert\Email(message: 'Ingrese una dirección de correo válida.')]
    #[Assert\Length(max: 50, maxMessage: 'El correo no puede superar los {{ limit }} caracteres.')]
    public ?string $email = null;

    #[Assert\Positive(message: 'Seleccione una presidencia válida.')]
    public ?int $presidenciaId = null;

    /**
     * @var list<string>
     */
    #[Assert\NotBlank(message: 'Seleccione al menos un rol.')]
    #[Assert\Count(min: 1, minMessage: 'Seleccione al menos un rol.')]
    #[Assert\Unique(message: 'No se permiten roles duplicados.')]
    #[Assert\All([
        new Assert\Choice(
            choices: Usuario::ALLOWED_ROLES,
            message: 'El rol seleccionado no está permitido.',
        ),
    ])]
    public array $roles = [];
}

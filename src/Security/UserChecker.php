<?php

/**
 * Verifica que el usuario no tenga baja lógica (fechaBaja) antes de permitir el login.
 */

namespace App\Security;

use App\Service\UsuarioService;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/* Este UserChecker es invocado antes y después de realizar la autenticación de un usuario.
    Se llama en el security.yaml, en el firewall donde se quiera ejecutar.
*/

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private UsuarioService $usuarioService,
    ) {
    }

    /**
     * checkPreAuth Se ejecuta justo antes de verificar la autenticación.
     *
     * @param UsuarioInterface $usuario
     */
    public function checkPreAuth(UserInterface $usuario): void
    {
        if (!$this->usuarioService->isUserEnabled($usuario->getUserIdentifier())) {
            throw new CustomUserMessageAuthenticationException('El usuario ingresado ha sido dado de baja. Comuníquese con la Oficina de Personal.');
        }
    }

    public function checkPostAuth(UserInterface $usuario): void
    {
        // Acá podríamos agregar chequeos adicionales después de la autenticación, si fuera necesario.
    }
}

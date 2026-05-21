<?php

/**
 * Registra acciones de usuarios (login, impersonación) en la tabla usuario_log_accion.
 */

namespace App\Service;

use App\Entity\Usuario;
use App\Entity\UsuarioLogAccion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UsuarioLogAccionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Método genérico para auditar cualquier acción.
     */
    private function auditar(
        UserInterface $usuario,
        string $accion,
        string $ip,
        ?Usuario $impersonateTo = null,
    ): void {
        $usuarioLogAccion = new UsuarioLogAccion();
        $usuarioLogAccion->setUsuario($usuario);
        $usuarioLogAccion->setIp($ip);
        $usuarioLogAccion->setAccion($accion);
        $usuarioLogAccion->setFechaHora(new \DateTime('now', new \DateTimeZone('America/Argentina/Cordoba')));

        if ($impersonateTo) {
            $usuarioLogAccion->setImpersonateTo($impersonateTo);
        }

        $this->entityManager->persist($usuarioLogAccion);
        $this->entityManager->flush();
    }

    /**
     * Audita login con un usuario específico (para usar desde el Success Handler).
     */
    public function logIn(Usuario $usuario, string $clientIp): void
    {
        $this->auditar(
            $usuario,
            'Login',
            $clientIp,
        );
    }

    /**
     * Audita inicio de suplantación para JWT (sin SwitchUserEvent).
     */
    public function impersonateIn(
        Usuario $fromUser,
        Usuario $toUser,
        string $ip,
    ): void {
        $this->auditar(
            $fromUser,
            'Inicia Suplantación de Usuario',
            $ip,
            $toUser
        );
    }

    /**
     * Audita fin de suplantación para JWT (sin SwitchUserEvent).
     */
    public function impersonateOut(
        Usuario $originalUser,
        Usuario $impersonatedUser,
        string $ip,
    ): void {
        $this->auditar(
            $originalUser,
            'Finaliza Suplantación de Usuario',
            $ip,
            $impersonatedUser
        );
    }
}

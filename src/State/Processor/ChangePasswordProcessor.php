<?php

/**
 * Processor de API Platform: valida el password actual, hashea el nuevo y notifica por email.
 */

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Usuario;
use App\Service\UsuarioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private EntityManagerInterface $em,
        private UsuarioService $usuarioService,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        // Obtener el usuario logueado desde el token JWT
        $usuario = $this->security->getUser();

        // Validación adicional por seguridad
        if (!$usuario instanceof Usuario) {
            throw new AccessDeniedHttpException('Usuario no autenticado');
        }

        // Verificar password actual
        if (!$this->hasher->isPasswordValid($usuario, $data->oldPassword)) {
            throw new UnprocessableEntityHttpException('La contraseña actual es incorrecta.');
        }

        // Actualizar password
        $hashedPassword = $this->hasher->hashPassword($usuario, $data->newPassword);
        $usuario->setPassword($hashedPassword);

        $this->em->flush();

        $this->usuarioService->sendEmailPassword(
            $usuario->getEmail(),
            $usuario->getUserIdentifier(),
            $data->newPassword,
            'RESET'
        );
    }
}

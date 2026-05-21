<?php

/**
 * Processor de API Platform: genera un password temporal para el usuario y lo envía por email.
 */

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Usuario;
use App\Service\UsuarioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private UsuarioService $usuarioService,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * process Método que se ejecuta al invocarse la operación personalizada para resetar la contraseña del usuario.
     *
     * @param mixed $data         es el cuerpo del request ya deserializado
     * @param mixed $operation    Es el objeto de metadata de la operación que se está ejecutando. Permite saber si es POST/PUT/PATCH, si tiene un name, si es collection o item, etc.
     * @param mixed $uriVariables contiene las variables capturadas de la ruta
     * @param mixed $context      Info adicional usada por el pipeline de API Platform. Contiene metadata como groups, resource_class, previous_data, request, etc.
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Usuario
    {
        // $data ES la entidad Usuario cargada por API Platform
        $usuario = $data;

        $newPassword = $this->usuarioService->generatePassword();

        $hashedPassword = $this->passwordHasher->hashPassword($usuario, $newPassword);
        $usuario->setPassword($hashedPassword);

        $this->em->flush();

        // Enviar email al usuario con los datos
        $this->usuarioService->sendEmailPassword(
            $usuario->getEmail(),
            $usuario->getUserIdentifier(),
            $newPassword,
            'RESET'
        );

        return $usuario;
    }
}

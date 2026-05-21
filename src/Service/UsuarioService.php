<?php

/**
 * Operaciones de ciclo de vida de usuarios: habilitación, login info, envío de emails con credenciales.
 */

namespace App\Service;

use App\Repository\UsuarioRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class UsuarioService
{
    public function __construct(private MailerInterface $mailer, private UsuarioRepository $usuarioRepository)
    {
    }

    /**
     * Verifica que exista un usuario.
     */
    public function isUser(string $username): bool
    {
        return $this->usuarioRepository->findOneBy(['username' => $username]) ? true : false;
    }

    /**
     * Verifica que existe un usuario y el mismo no se encuentre dado de baja.
     */
    public function isUserEnabled(string $username): bool
    {
        $user = $this->usuarioRepository->findOneBy(['username' => $username]);

        return $user && $user->getFechaBaja() === null;
    }

    /**
     * Actualiza fecha de última conexión e incrementa el contador de conexiones.
     */
    public function updateLoggingInformation(string $username): void
    {
        $user = $this->usuarioRepository->findOneBy(['username' => $username]);
        if ($user) {
            $this->usuarioRepository->actualizarLogueoUsuario($user);
        }
    }

    /**
     * Notifica por Correo password al Usuario.
     */
    public function sendEmailPassword(string $email, string $usuario, string $password, string $action = 'NEW'): bool
    {
        $mailTemplate = null;

        if ($action == 'NEW') {
            $mailTemplate = 'admin/usuario/mail_new.html.twig';
        }

        if ($action == 'RESET') {
            $mailTemplate = 'admin/usuario/mail_reset.html.twig';
        }

        try {
            $fromAdrress = $_ENV['MAIL_FROM'];
            $email = (new TemplatedEmail())
                ->from($fromAdrress)
                ->to($email)
                ->subject('Poder Judicial Santa Fe - Sistema de Cursos CCJ')
                ->htmlTemplate($mailTemplate)
                ->context([
                    'expiration_date' => new \DateTime('+7 days'),
                    'usuario' => $usuario,
                    'password' => $password,
                ]);
            $this->mailer->send($email);

            return true;
        } catch (\Symfony\Component\Mime\Exception\RfcComplianceException $e) {
            return false;
        }
    }

    /*
     * Genera password del Usuario
     */
    public function generatePassword(): string
    {
        $password = bin2hex(random_bytes(8));

        return $password;
    }
}

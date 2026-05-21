<?php

/**
 * Suplantación de usuarios vía JWT: genera tokens con claim 'impersonatingFrom' y recupera el usuario original.
 */

namespace App\Service;

use App\Entity\Usuario;
use App\Repository\UsuarioRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTImpersonationService
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private UsuarioRepository $usuarioRepository,
        private UsuarioLogAccionService $usuarioLogAccionService,
        private Security $security,
    ) {
    }

    /**
     * impersonate Genera un token para $toUser añadiendo un claim que indica la suplantación.
     */
    public function impersonate(UserInterface $fromUser, string $toUserId, string $ip): string
    {
        $toUser = $this->usuarioRepository->find($toUserId);

        if (!$toUser) {
            throw new \InvalidArgumentException('Usuario objetivo no encontrado');
        }

        // Auditar suplantación
        $this->usuarioLogAccionService->impersonateIn($fromUser, $toUser, $ip);

        // Agregar un payload extra al token, para identificar la suplantación.
        // Ese payload es el username del usuario original
        $extraPayload = [
            'impersonatingFrom' => $fromUser->getUserIdentifier(),
        ];

        // Crear el nuevo token, con el usuario a impersonar, y el extra payload que contiene el usuario original
        // createFromPayload es un método de la librería de Lexik para crear tokens con la clave privada generada.
        $newToken = $this->jwtManager->createFromPayload($toUser, $extraPayload);

        return $newToken;
    }

    /**
     * exitImpersonation Regenera token para el usuario original y audita fin.
     *
     * @return string $token
     */
    public function exitImpersonation(UserInterface $originalUser, UserInterface $impersonatedUser, string $ip): string
    {
        // Auditar fin de la suplantación
        $this->usuarioLogAccionService->impersonateOut($originalUser, $impersonatedUser, $ip);

        // Generar token normal para el original. No se agrega ningún claim.
        $newToken = $this->jwtManager->create($originalUser);

        return $newToken;
    }

    /**
     * extractPayloadFromRequest Extrae el payload del token JWT desde la request.
     */
    public function extractPayloadFromRequest(Request $request): array
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new \RuntimeException('Token no encontrado en el header Authorization');
        }

        $token = substr($authHeader, 7);

        try {
            return $this->jwtManager->parse($token);
        } catch (\Exception $e) {
            throw new \RuntimeException('Token inválido: '.$e->getMessage());
        }
    }

    public function getPayloadFromTokenStorage(): ?array
    {
        $token = $this->security->getToken();

        if (!$token) {
            return null;
        }

        // Seguridad: evitar excepciones de atributos inexistentes
        if (method_exists($token, 'hasAttribute')) {
            if ($token->hasAttribute('payload')) {
                return $token->getAttribute('payload');
            }

            if ($token->hasAttribute('jwt_payload')) {
                return $token->getAttribute('jwt_payload');
            }
        }

        return null;
    }

    /**
     * Obtiene el Usuario Actual
     * En caso de estar impersonando, obtiene el Usuario original (el que impersona y no el impersonado).
     */
    public function getRealUserFromPayload(?array $payload = null): ?Usuario
    {
        if (!$payload) {
            $payload = $this->getPayloadFromTokenStorage();

            if (!$payload) {
                $user = $this->security->getUser();

                return $user instanceof Usuario ? $user : null;
            }
        }

        // Caso impersonación
        if (!empty($payload['impersonating_from'])) {
            return $this->usuarioRepository->find($payload['impersonating_from']);
        }

        // Usuario normal
        if (!empty($payload['username'])) {
            return $this->usuarioRepository->findOneBy(['username' => $payload['username']]);
        }

        $user = $this->security->getUser();

        return $user instanceof Usuario ? $user : null;
    }
}

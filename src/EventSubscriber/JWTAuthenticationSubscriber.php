<?php

/**
 * Actualiza datos de acceso del usuario en cada login JWT e ignora tokens de impersonación.
 */

namespace App\EventSubscriber;

use App\Entity\Usuario;
use App\Service\JWTImpersonationService;
use App\Service\UsuarioLogAccionService;
use App\Service\UsuarioService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class JWTAuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UsuarioService $usuarioService,
        private UsuarioLogAccionService $usuarioLogAccionService,
        private RequestStack $requestStack,
        private JWTImpersonationService $jwtImpersonationService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Se dispara DESPUÉS de crear el token, justo antes de devolverlo
        return [
            Events::JWT_CREATED => 'onJWTCreated',
        ];
    }

    /**
     * Se ejecuta cuando se crea un token JWT (login exitoso)
     * Aquí tenemos acceso al Request completo.
     */
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $data = $event->getData(); // payload final del JWT
        $user = $event->getUser();

        if (!$user instanceof Usuario) {
            return;
        }

        // Si ES un token de suplantación → no auditar login
        if (!empty($data['impersonatingFrom'])) {
            return;
        }

        // Login normal → auditar
        $request = $this->requestStack->getCurrentRequest();
        $clientIp = $request ? $request->getClientIp() : 'unknown';

        $this->usuarioService->updateLoggingInformation($user->getUsername());
        $this->usuarioLogAccionService->logIn($user, $clientIp);
    }
}

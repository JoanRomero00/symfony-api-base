<?php

namespace App\EventListener;

use App\Entity\Usuario;
use App\Service\UsuarioLogAccionService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class, dispatcher: 'security.event_dispatcher.api')]
final class JsonLogoutListener
{
    public function __construct(
        private readonly UsuarioLogAccionService $usuarioLogAccionService,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if ($token) {
            $user = $token->getUser();
            if ($user instanceof Usuario) {
                $request = $event->getRequest();
                $clientIp = $request ? $request->getClientIp() : 'unknown';
                $this->usuarioLogAccionService->logOut($user, $clientIp);
            }
        }

        // Return JSON instead of redirecting
        $response = new JsonResponse([
            'message' => 'Sesión cerrada correctamente.',
        ], Response::HTTP_OK);

        $event->setResponse($response);
    }
}

<?php

/**
 * Agrega el header Retry-After en respuestas 429 del rate limiter de login.
 */

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * Devuelve HTTP 429 + Retry-After cuando el login_throttling del firewall login
 * bloquea por demasiados intentos (issue #77).
 *
 * Lexik por default mapea cualquier AuthenticationException a 401. Este listener
 * intercepta el evento oficial de Lexik y solo sobrescribe la respuesta para el
 * caso de throttling — el resto de errores siguen el flujo normal del bundle.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_failure')]
final class LoginThrottleResponseListener
{
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $exception = $event->getException();
        if (!$exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return;
        }

        $message = strtr($exception->getMessageKey(), $exception->getMessageData());
        $response = new JWTAuthenticationFailureResponse($message, Response::HTTP_TOO_MANY_REQUESTS);

        $minutes = (int) ($exception->getMessageData()['%minutes%'] ?? 1);
        $response->headers->set('Retry-After', (string) ($minutes * 60));

        $event->setResponse($response);
    }
}

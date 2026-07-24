<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class UsuarioValidationResponseSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['normalizeValidationResponse', -100],
        ];
    }

    public function normalizeValidationResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (422 !== $response->getStatusCode() || !str_starts_with($request->getPathInfo(), '/api/usuarios')) {
            return;
        }

        $payload = json_decode((string) $response->getContent(), true);
        if (!is_array($payload) || !isset($payload['violations']) || !is_array($payload['violations'])) {
            return;
        }

        $errors = [];
        foreach ($payload['violations'] as $violation) {
            $field = $violation['propertyPath'] ?? null;
            $message = $violation['message'] ?? null;
            if (is_string($field) && is_string($message)) {
                $errors[$field][] = $message;
            }
        }

        $payload['message'] = 'Los datos ingresados no son válidos.';
        $payload['errors'] = $errors;
        $response->setContent((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

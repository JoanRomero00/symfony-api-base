<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiPaginationParameterSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['normalizePaginationParameter', 64],
        ];
    }

    public function normalizePaginationParameter(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->isMethod('GET') || !str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        if ($request->query->has('itemsPerPage') && !$request->query->has('limit')) {
            $request->query->set('limit', $request->query->get('itemsPerPage'));
        }
    }
}

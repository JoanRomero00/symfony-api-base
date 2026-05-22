<?php

/**
 * Placeholder para el firewall public_api (X-API-Key).
 * Reemplazar con los endpoints reales del proyecto.
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class PublicApiController extends AbstractController
{
    #[Route('/api/publica/recursos', name: 'api_publica_recursos', methods: ['GET'])]
    public function recursos(): JsonResponse
    {
        return new JsonResponse(['data' => []]);
    }
}

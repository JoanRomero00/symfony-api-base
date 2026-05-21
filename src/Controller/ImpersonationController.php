<?php

/**
 * Controlador de suplantación: genera tokens JWT para impersonar usuarios y para volver al original.
 */

namespace App\Controller;

use App\Dto\ImpersonationDto;
use App\Repository\UsuarioRepository;
use App\Service\JWTImpersonationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ImpersonationController extends AbstractController
{
    #[IsGranted('ROLE_ALLOWED_TO_SWITCH')]
    public function impersonate(string $idUsuario, Request $req, JWTImpersonationService $jwtImpersonationService): JsonResponse
    {
        $from = $this->getUser();

        if (!$from) {
            return new JsonResponse(['error' => 'no autenticado'], 401);
        }

        try {
            $token = $jwtImpersonationService->impersonate(
                $from,
                $idUsuario,
                $req->getClientIp()
            );

            // Se tipa la respuesta usando el DTO creado al efecto (ImpersonationDto)
            $responseDto = new ImpersonationDto($token);

            return new JsonResponse($responseDto);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function exit(Request $req, JWTImpersonationService $jwtImpersonationService, UsuarioRepository $usuarioRepository): JsonResponse
    {
        $current = $this->getUser();

        if (!$current) {
            return new JsonResponse(['error' => 'no autenticado'], 401);
        }

        try {
            // Extraer payload usando el servicio
            $payload = $jwtImpersonationService->extractPayloadFromRequest($req);

            // Verificar que hay una impersonalización activa
            if (!isset($payload['impersonatingFrom'])) {
                return new JsonResponse(['error' => 'no hay suplantación activa'], 400);
            }

            // Buscar el usuario original
            $original = $usuarioRepository->findOneBy(['username' => $payload['impersonatingFrom']]);

            if (!$original) {
                return new JsonResponse(['error' => 'usuario original no encontrado'], 400);
            }

            $newToken = $jwtImpersonationService->exitImpersonation(
                $original,
                $current,
                $req->getClientIp()
            );

            $responseDto = new ImpersonationDto($newToken);

            return new JsonResponse($responseDto);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 401);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}

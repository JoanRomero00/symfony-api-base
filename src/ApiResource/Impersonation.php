<?php

/**
 * Recurso virtual de API Platform que expone los endpoints de suplantación de usuarios.
 */

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\ImpersonationController;
use App\Dto\ImpersonationDto;

#[ApiResource(
    operations: [
        new Post(
            name: 'api_impersonacion_iniciar',
            uriTemplate: '/impersonacion/iniciar/{idUsuario}',
            controller: ImpersonationController::class.'::impersonate',
            security: "is_granted('ROLE_ALLOWED_TO_SWITCH')", // Solo documentación OpenAPI — la seguridad real está en el controller (#[IsGranted])
            read: false,
            write: false,
            input: false,
            output: ImpersonationDto::class,
        ),
        new Post(
            name: 'api_impersonacion_finalizar',
            uriTemplate: '/impersonacion/finalizar',
            controller: ImpersonationController::class.'::exit',
            read: false,
            write: false,
            input: false,
            output: ImpersonationDto::class
        ),
    ])]
class Impersonation
{
}

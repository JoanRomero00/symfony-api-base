<?php

/**
 * Recurso virtual de API Platform que expone las operaciones del sistema de auditoría.
 */

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\Controller\AuditoriaController;
use App\Dto\AuditoriaConfigItemDto;
use App\Dto\AuditoriaReporteQueryDto;
use App\Dto\ReporteActividadQueryDto;

#[ApiResource(
    security: "is_granted('ROLE_AUDIT')", // Solo documentación OpenAPI — la seguridad real está en el controller (#[IsGranted])
    operations: [
        new GetCollection(
            name: 'api_auditoria_config',
            uriTemplate: '/auditoria/config',
            controller: AuditoriaController::class.'::config',
            read: false,
            write: false,
            output: AuditoriaConfigItemDto::class,
        ),
        new Post(
            name: 'api_auditoria_activar',
            uriTemplate: '/auditoria/{nombreTabla}/activar',
            controller: AuditoriaController::class.'::activate',
            read: false,
            write: false,
            input: false
        ),
        new Post(
            name: 'api_auditoria_pausar',
            uriTemplate: '/auditoria/{nombreTabla}/pausar',
            controller: AuditoriaController::class.'::pause',
            read: false,
            write: false,
            input: false
        ),
        new Post(
            name: 'api_auditoria_reanudar',
            uriTemplate: '/auditoria/{nombreTabla}/reanudar',
            controller: AuditoriaController::class.'::resume',
            read: false,
            write: false,
            input: false
        ),
        new Post(
            name: 'api_auditoria_eliminar',
            uriTemplate: '/auditoria/{nombreTabla}/eliminar',
            controller: AuditoriaController::class.'::delete',
            read: false,
            write: false,
            input: false
        ),
        new Post(
            name: 'api_auditoria_activar_todas',
            uriTemplate: '/auditoria/activar-todas',
            controller: AuditoriaController::class.'::activateAll',
            read: false,
            write: false,
            input: false
        ),
        new Post(
            name: 'api_auditoria_pausar_todas',
            uriTemplate: '/auditoria/pausar-todas',
            controller: AuditoriaController::class.'::pauseAll',
            read: false,
            write: false,
            input: false
        ),
        new Post(
            name: 'api_auditoria_reanudar_todas',
            uriTemplate: '/auditoria/reanudar-todas',
            controller: AuditoriaController::class.'::resumeAll',
            read: false,
            write: false,
            input: false
        ),
        new Post(
            name: 'api_auditoria_eliminar_todas',
            uriTemplate: '/auditoria/eliminar-todas',
            controller: AuditoriaController::class.'::deleteAll',
            read: false,
            write: false,
            input: false
        ),
        new Get(
            name: 'api_auditoria_reporte',
            uriTemplate: '/auditoria/reporte-auditoria',
            controller: AuditoriaController::class.'::printReporteAuditoria',
            read: false,
            write: false,
            input: AuditoriaReporteQueryDto::class,
            formats: ['pdf' => ['application/pdf']],
            openapi: new Operation(
                summary: 'Genera PDF de auditoría',
                parameters: [
                    new Parameter(name: 'entidad', in: 'query', schema: ['type' => 'string']),
                    new Parameter(name: 'fechaDesde', in: 'query', schema: ['type' => 'string']),
                    new Parameter(name: 'fechaHasta', in: 'query', schema: ['type' => 'string']),
                ],
            )
        ),
        new Get(
            name: 'api_auditoria_reporte_actividad',
            uriTemplate: '/auditoria/reporte-actividad',
            controller: AuditoriaController::class.'::printReporteActividad',
            read: false,
            write: false,
            input: ReporteActividadQueryDto::class,
            formats: ['pdf' => ['application/pdf']],
            openapi: new Operation(
                summary: 'Genera PDF del reporte de actividad de auditoría',
                parameters: [
                    new Parameter(name: 'fechaDesde', in: 'query', schema: ['type' => 'string']),
                    new Parameter(name: 'fechaHasta', in: 'query', schema: ['type' => 'string']),
                ],
            )
        ),
    ])]
class Auditoria
{
}

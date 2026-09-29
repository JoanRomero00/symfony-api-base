<?php

/**
 * Controlador del sistema de auditoría: configuración, activación/pausa y generación de reportes PDF.
 */

namespace App\Controller;

use ApiPlatform\State\Pagination\ArrayPaginator;
use App\Dto\AuditoriaReporteQueryDto;
use App\Dto\ReporteActividadQueryDto;
use App\Service\AppService;
use App\Service\AuditoriaService;
use App\Service\BasePdfService;
use App\Service\ReportGeneratorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[IsGranted('ROLE_AUDIT')]
class AuditoriaController extends AbstractController
{
    public function __construct(private AuditoriaService $auditoriaService,
        private NormalizerInterface $normalizer, private AppService $appService)
    {
    }

    public function config(Request $request): JsonResponse
    {
        $items = $this->auditoriaService->getConfig();

        // 1. Aplicar Filtros y Orden
        $items = $this->applySearch($items, $request->query->get('q'));
        $items = $this->applyOrder($items, $request->query->all('order'));

        // Paginación y Normalización
        // Es importante re-indexar el array con array_values después de filtrar
        $items = array_values($items);

        $paginator = new ArrayPaginator($items, 0, count($items));
        $data = $this->normalizer->normalize($paginator, 'json');

        return new JsonResponse($data);
    }

    public function events(string $nombreTabla, Request $request): JsonResponse
    {
        $config = $this->findTableConfig($nombreTabla);
        if (!$config) {
            return new JsonResponse([
                'success' => false,
                'message' => "La tabla {$nombreTabla} no pertenece a una entidad gestionada por el sistema.",
            ], Response::HTTP_NOT_FOUND);
        }
        if (!$config['existTableAudit']) {
            return new JsonResponse([
                'success' => false,
                'message' => "La auditoría de {$nombreTabla} no está activada.",
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $fechaDesde = $this->readDateFilter($request, 'fechaDesde', false);
            $fechaHasta = $this->readDateFilter($request, 'fechaHasta', true);
            if ($fechaDesde && $fechaHasta && $fechaDesde > $fechaHasta) {
                throw new \InvalidArgumentException('La fecha desde no puede ser posterior a la fecha hasta.');
            }
            $registroId = $this->readPositiveIntegerFilter($request, 'registroId');
            $usuarioId = $this->readPositiveIntegerFilter($request, 'usuarioId');
            $limite = $this->readPositiveIntegerFilter($request, 'limite') ?? 100;
            if ($limite > 500) {
                throw new \InvalidArgumentException('El límite máximo de resultados es 500.');
            }

            $operacion = strtoupper((string) $request->query->get('operacion', ''));
            if ('' !== $operacion && !in_array($operacion, ['I', 'U', 'D'], true)) {
                throw new \InvalidArgumentException('La operación debe ser I, U o D.');
            }

            $events = $this->auditoriaService->getAuditoria(
                $config['entidad'],
                $fechaDesde,
                $fechaHasta,
                $registroId,
                $usuarioId,
                '' !== $operacion ? $operacion : null,
                $limite,
                true,
                true,
            );
        } catch (\InvalidArgumentException|\LogicException $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $items = array_map(static fn (array $event): array => [
            'id' => (int) $event['id'],
            'fecha' => $event['audit_timestamp'],
            'usuarioId' => null !== $event['audit_iduserapp'] ? (int) $event['audit_iduserapp'] : null,
            'usuario' => $event['username'],
            'registroId' => (int) $event['audit_identity'],
            'operacion' => $event['audit_action'],
            'operacionCodigo' => $event['audit_action_code'],
            'datosAnteriores' => self::decodeJsonValue($event['old_data']),
            'datosNuevos' => self::decodeJsonValue($event['new_data']),
            'diferencias' => $event['diferencias'] ?? [],
        ], $events);

        return new JsonResponse([
            'items' => $items,
            'total' => isset($events[0]) ? (int) $events[0]['total_registros'] : 0,
            'limite' => $limite,
        ]);
    }

    public function activate(string $nombreTabla): JsonResponse
    {
        $config = $this->findTableConfig($nombreTabla);
        if (!$config) {
            return $this->invalidAction("La tabla {$nombreTabla} no pertenece a una entidad gestionada por el sistema.");
        }
        if (!$config['isAuditable'] || true !== $config['isAudited']) {
            return $this->invalidAction("La entidad {$config['entidad']} no está configurada para auditoría.");
        }
        if ($config['existTableAudit']) {
            return $this->invalidAction("La auditoría de {$nombreTabla} ya fue activada.");
        }

        $error = $this->auditoriaService->activate($nombreTabla);

        return $this->actionResult($error, "Auditoría activada para {$nombreTabla}");
    }

    public function pause(string $nombreTabla): JsonResponse
    {
        $config = $this->findTableConfig($nombreTabla);
        if (!$config) {
            return $this->invalidAction("La tabla {$nombreTabla} no pertenece a una entidad gestionada por el sistema.");
        }
        if (!$config['existTableAudit'] || !$config['existTriggerAudit']) {
            return $this->invalidAction("La auditoría de {$nombreTabla} no está activa.");
        }

        $error = $this->auditoriaService->pause($nombreTabla);

        return $this->actionResult($error, "Auditoría pausada para {$nombreTabla}");
    }

    public function resume(string $nombreTabla): JsonResponse
    {
        $config = $this->findTableConfig($nombreTabla);
        if (!$config) {
            return $this->invalidAction("La tabla {$nombreTabla} no pertenece a una entidad gestionada por el sistema.");
        }
        if (!$config['isAuditable'] || true !== $config['isAudited']) {
            return $this->invalidAction("La entidad {$config['entidad']} no está configurada para auditoría.");
        }
        if (!$config['existTableAudit'] || $config['existTriggerAudit']) {
            return $this->invalidAction("La auditoría de {$nombreTabla} no está pausada.");
        }

        $error = $this->auditoriaService->resume($nombreTabla);

        return $this->actionResult($error, "Auditoría reanudada para {$nombreTabla}");
    }

    public function delete(string $nombreTabla): JsonResponse
    {
        $config = $this->findTableConfig($nombreTabla);
        if (!$config) {
            return $this->invalidAction("La tabla {$nombreTabla} no pertenece a una entidad gestionada por el sistema.");
        }
        if (!$config['existTableAudit']) {
            return $this->invalidAction("La auditoría de {$nombreTabla} no está activada.");
        }
        if ($config['existTriggerAudit']) {
            return $this->invalidAction("Primero debe pausar la auditoría de {$nombreTabla}.");
        }

        $error = $this->auditoriaService->delete($nombreTabla);

        return $this->actionResult($error, "Registros de auditoría eliminados para {$nombreTabla}");
    }

    public function activateAll(): JsonResponse
    {
        $count = 0;
        foreach ($this->auditoriaService->getConfig() as $entity) {
            if ($entity['isAudited'] && $entity['isAuditable'] && !$entity['existTableAudit'] && !$entity['existTriggerAudit']) {
                $this->auditoriaService->activate($entity['tableName']);
                ++$count;
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => "Activadas {$count} entidades",
            'count' => $count,
        ]);
    }

    public function pauseAll(): JsonResponse
    {
        $count = 0;
        foreach ($this->auditoriaService->getConfig() as $entity) {
            if ($entity['existTableAudit'] && $entity['existTriggerAudit']) {
                $this->auditoriaService->pause($entity['tableName']);
                ++$count;
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => "Pausadas {$count} entidades",
            'count' => $count,
        ]);
    }

    public function resumeAll(): JsonResponse
    {
        $count = 0;
        foreach ($this->auditoriaService->getConfig() as $entity) {
            if ($entity['isAuditable'] && $entity['existTableAudit'] && !$entity['existTriggerAudit']) {
                $this->auditoriaService->resume($entity['tableName']);
                ++$count;
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => "Reactivadas {$count} entidades",
            'count' => $count,
        ]);
    }

    public function deleteAll(): JsonResponse
    {
        $count = 0;
        foreach ($this->auditoriaService->getConfig() as $entity) {
            if ($entity['isAudited'] && $entity['isAuditable'] && $entity['existTableAudit'] && !$entity['existTriggerAudit']) {
                $this->auditoriaService->delete($entity['tableName']);
                ++$count;
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => "Borradas {$count} entidades de auditoría",
            'count' => $count,
        ]);
    }

    public function printReporteAuditoria(#[MapQueryString()] AuditoriaReporteQueryDto $auditoriaReporteQueryDto, ReportGeneratorService $reportGeneratorService, BasePdfService $pdf): Response
    {
        try {
            // El servicio procesa la lógica de negocio y copetes
            // El dto ya tiene todos los datos cargados, que son todos los query strings ingresados por el usuario
            $data = $reportGeneratorService->generarReporteAuditoriaHtml($auditoriaReporteQueryDto);

            $html = $this->renderView('reportes/auditoria/printAuditoriaSistema.html.twig', $data);

            return new Response(
                $pdf->getPdf()->getOutputFromHtml($html),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="Informe_Auditoria.pdf"',
                ]
            );
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function printReporteActividad(#[MapQueryString()] ReporteActividadQueryDto $dto, ReportGeneratorService $reportGeneratorService, BasePdfService $pdf): Response
    {
        try {
            // El servicio procesa la lógica de negocio y copetes
            // El dto ya tiene todos los datos cargados, que son todos los query strings ingresados por el usuario
            $data = $reportGeneratorService->generarReporteActividadHtml($dto);

            $html = $this->renderView('reportes/auditoria/printReporteActividad.html.twig', $data);

            // Generar el nombre del archivo
            $fileName = $this->appService->sanitizarNombreArchivo(
                sprintf('Informe de Actividad %s al %s.pdf', $dto->fechaDesde, $dto->fechaHasta)
            );

            return new Response(
                $pdf->getPdf()->getOutputFromHtml($html),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="'.$fileName.'"',
                ]
            );
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Filtra el array por el parámetro de búsqueda 'q'.
     */
    private function applySearch(array $items, ?string $q): array
    {
        if (empty($q)) {
            return $items;
        }

        $q = strtolower($q);

        return array_filter($items, fn ($item) => str_contains(strtolower($item['entidad'] ?? ''), $q)
            || str_contains(strtolower($item['tableName'] ?? ''), $q)
        );
    }

    /**
     * Ordena el array según los parámetros de 'order'.
     */
    private function applyOrder(array $items, array $order): array
    {
        if (empty($order)) {
            return $items;
        }

        foreach ($order as $field => $direction) {
            usort($items, function ($a, $b) use ($field, $direction) {
                $valA = $a[$field] ?? '';
                $valB = $b[$field] ?? '';

                $res = is_numeric($valA) && is_numeric($valB)
                    ? $valA <=> $valB
                    : strcasecmp((string) $valA, (string) $valB);

                return strtolower($direction) === 'desc' ? -$res : $res;
            });
        }

        return $items;
    }

    private function findTableConfig(string $tableName): ?array
    {
        foreach ($this->auditoriaService->getConfig() as $config) {
            if ($config['tableName'] === $tableName) {
                return $config;
            }
        }

        return null;
    }

    private function readDateFilter(Request $request, string $name, bool $endOfDay): ?string
    {
        $value = trim((string) $request->query->get($name, ''));
        if ('' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || (false !== $errors && (0 < $errors['warning_count'] || 0 < $errors['error_count']))) {
            throw new \InvalidArgumentException("El filtro {$name} debe tener formato AAAA-MM-DD.");
        }

        return $date->format('Y-m-d').($endOfDay ? ' 23:59:59' : ' 00:00:00');
    }

    private function readPositiveIntegerFilter(Request $request, string $name): ?int
    {
        $value = trim((string) $request->query->get($name, ''));
        if ('' === $value) {
            return null;
        }
        if (!ctype_digit($value) || 1 > (int) $value) {
            throw new \InvalidArgumentException("El filtro {$name} debe ser un número entero positivo.");
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeJsonValue(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function invalidAction(string $message): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
        ]);
    }

    private function actionResult(string $error, string $successMessage): JsonResponse
    {
        return new JsonResponse([
            'success' => '' === $error,
            'message' => $error ?: $successMessage,
        ]);
    }
}

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

    public function activate(string $nombreTabla): JsonResponse
    {
        $error = $this->auditoriaService->activate($nombreTabla);

        return new JsonResponse([
            'success' => $error ? false : true,
            'message' => $error ?: "Auditoría activada para {$nombreTabla}",
        ]);
    }

    public function pause(string $nombreTabla): JsonResponse
    {
        $error = $this->auditoriaService->pause($nombreTabla);

        return new JsonResponse([
            'success' => $error ? false : true,
            'message' => $error ?: "Auditoría pausada para {$nombreTabla}",
        ]);
    }

    public function resume(string $nombreTabla): JsonResponse
    {
        $error = $this->auditoriaService->resume($nombreTabla);

        return new JsonResponse([
            'success' => $error ? false : true,
            'message' => $error ?: "Auditoría reanudada para {$nombreTabla}",
        ]);
    }

    public function delete(string $nombreTabla): JsonResponse
    {
        $error = $this->auditoriaService->delete($nombreTabla);

        return new JsonResponse([
            'success' => $error ? false : true,
            'message' => $error ?: "Registros eliminados para {$nombreTabla}",
        ]);
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
}

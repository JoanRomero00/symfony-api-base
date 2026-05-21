<?php

/**
 * Genera datos y HTML para los reportes PDF del sistema de auditoría y actividad de usuarios.
 */

namespace App\Service;

use App\Dto\AuditoriaReporteQueryDto;
use App\Dto\ReporteActividadQueryDto;
use App\Repository\UsuarioRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class ReportGeneratorService
{
    public function __construct(
        private AuditoriaService $auditoriaService,
        private UsuarioRepository $usuarioRepository,
        private ContainerBagInterface $contenedorParametros,
    ) {
    }

    public function generarReporteAuditoriaHtml(AuditoriaReporteQueryDto $dto): array
    {
        /* 1. Recuperar parámetros directamente del objeto DTO */
        // Como son propiedades públicas del objeto, accedemos con ->
        $entidadNombre = $dto->entidad;
        $registroEntidadId = $dto->registroEntidadId;
        $idUsuario = $dto->usuarioId;

        // Lógica de "todas": si el DTO trae un valor distinto a 'todas', lo usamos
        $tipoOperacion = ($dto->tipoOperacion !== 'todas') ? $dto->tipoOperacion : null;

        $fechaDesde = $dto->fechaDesde;
        $fechaHasta = $dto->fechaHasta;

        // El DTO ya tiene el valor por defecto de 100 si no se envía nada
        $limiteResultados = $dto->limiteResultados;

        // El DTO ya convierte 'true'/'false' a booleano real mediante MapQueryString
        $incluirModificaciones = $dto->incluirModificaciones;

        if (!$entidadNombre) {
            throw new \LogicException('Debe especificar una entidad para el reporte.');
        }

        // --- Armado de Filtro 1 ---
        $descripcionFiltro1 = 'Entidad: "'.$this->auditoriaService->getTableName($entidadNombre).'"';

        if ($registroEntidadId) {
            $descripcionFiltro1 .= ', ID de Registro N° '.$registroEntidadId;
        }

        if ($idUsuario) {
            $usuario = $this->usuarioRepository->find($idUsuario);
            $descripcionFiltro1 .= ', Usuario: '.($usuario ?: 'No encontrado');
        }

        if ($tipoOperacion) {
            $tipos = $this->auditoriaService->getTiposOperacion();
            $descripcionFiltro1 .= ', Tipo de Operación: '.($tipos[$tipoOperacion] ?? $tipoOperacion);
        }

        if ($fechaDesde && $fechaHasta) {
            $descripcionFiltro1 .= ', Desde: '.$fechaDesde.' Hasta: '.$fechaHasta;
        }

        // --- Armado de Filtro 2 ---
        $descripcionFiltro2 = $incluirModificaciones ? 'Incluyendo detalle de las Modificaciones' : '';
        if ($limiteResultados) {
            $descripcionFiltro2 .= ($descripcionFiltro2 ? '. ' : '').'Limitando Informe a los primeros '.$limiteResultados.' registros.';
        }

        // --- Consulta ---
        $auditoria = $this->auditoriaService->getAuditoria(
            $entidadNombre,
            $fechaDesde,
            $fechaHasta,
            $registroEntidadId,
            $idUsuario,
            $tipoOperacion,
            $limiteResultados,
            $incluirModificaciones
        );

        return [
            'subtitulo' => 'Informe de Auditoría',
            'copete1' => 'Filtros utilizados: '.$descripcionFiltro1,
            'copete2' => $descripcionFiltro2,
            'auditoria' => $auditoria,
            'incluirModificaciones' => $incluirModificaciones,
            'diffFull' => (bool) $registroEntidadId,
        ];
    }

    public function generarReporteActividadHtml(ReporteActividadQueryDto $dto): array
    {
        // 1. Obtener y Procesar Datos
        $activity = $this->auditoriaService->obtainActivy($dto->fechaDesde, $dto->fechaHasta); // , $dto->oficinaId);

        // Tablas principales
        $tablasPrincipales = $this->contenedorParametros->get('app.audit.tablas_principales');
        $schemaAuditName = $this->contenedorParametros->get('app.audit_shemma_audit_name');

        foreach ($activity as &$entidad) {
            $nombre = ucwords(str_replace([$schemaAuditName.'.', '_', 'audit'], ['', ' ', ''], $entidad['audit_table']));
            $entidad['audit_table'] = $nombre;
            $entidad['principal'] = in_array(trim($nombre), $tablasPrincipales);
        }

        // 2. Preparar Vista
        // $oficinaNombre = $dto->oficinaId > 0 ? $this->oficinaRepository->find($dto->oficinaId) : 'todas las Oficinas';

        return ['subtitulo' => 'Informe de Actividad', // ($oficinaNombre)",
            'copete2' => "Desde el {$dto->fechaDesde} al {$dto->fechaHasta}",
            'activity' => $activity,
        ];
    }
}

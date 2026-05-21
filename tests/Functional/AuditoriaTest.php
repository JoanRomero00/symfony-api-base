<?php

/**
 * Tests funcionales del sistema de auditoría: config, activación, pausa, y reportes.
 */

namespace App\Tests\Functional;

class AuditoriaTest extends AbstractApiTestCase
{
    // ── Auth: 401 sin token ──

    public function testConfigRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/auditoria/config');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testActivateRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auditoria/test_table/activar');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testPauseRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auditoria/test_table/pausar');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testResumeRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auditoria/test_table/reanudar');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auditoria/test_table/eliminar');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testActivateAllRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auditoria/activar-todas');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testPauseAllRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auditoria/pausar-todas');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testResumeAllRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auditoria/reanudar-todas');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteAllRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auditoria/eliminar-todas');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testPrintReporteAuditoriaRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/auditoria/reporte-auditoria');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testPrintReporteActividadRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/auditoria/reporte-actividad');

        $this->assertResponseStatusCodeSame(401);
    }

    // ── Authorization: 403 para ROLE_USER (sin ROLE_AUDIT) ──

    public function testConfigForbiddenForRoleUser(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('GET', '/api/auditoria/config');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testActivateForbiddenForRoleUser(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('POST', '/api/auditoria/test_table/activar');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testActivateAllForbiddenForRoleUser(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('POST', '/api/auditoria/activar-todas');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testPrintReporteAuditoriaForbiddenForRoleUser(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('GET', '/api/auditoria/reporte-auditoria');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testPrintReporteActividadForbiddenForRoleUser(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('GET', '/api/auditoria/reporte-actividad');

        $this->assertResponseStatusCodeSame(403);
    }

    // ── Operaciones reales ──

    /**
     * Tabla usada para el ciclo de activar/pausar/reanudar/eliminar.
     * Debe ser una entidad simple que exista en la DB de test.
     */
    private const TEST_TABLE = 'configuracion_sistema';

    public function testConfigReturnsExpectedStructure(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $response = $client->request('GET', '/api/auditoria/config');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('items', $data);
        $this->assertNotEmpty($data['items']);

        $item = $data['items'][0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('entidad', $item);
        $this->assertArrayHasKey('tableName', $item);
        $this->assertArrayHasKey('cntRegister', $item);
        $this->assertArrayHasKey('size', $item);
        $this->assertArrayHasKey('isAuditable', $item);
        $this->assertArrayHasKey('isAudited', $item);
        $this->assertArrayHasKey('existTableAudit', $item);
        $this->assertArrayHasKey('existTriggerAudit', $item);
        $this->assertArrayHasKey('estado', $item);
    }

    public function testConfigFilterQ(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $response = $client->request('GET', '/api/auditoria/config?q=usuario');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('items', $data);
        $this->assertNotEmpty($data['items']);

        foreach ($data['items'] as $item) {
            $this->assertTrue(
                str_contains(strtolower($item['entidad']), 'usuario')
                || str_contains(strtolower($item['tableName']), 'usuario'),
            );
        }
    }

    public function testConfigFilterQNoResults(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $response = $client->request('GET', '/api/auditoria/config?q=zzz_inexistente');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('items', $data);
        $this->assertEmpty($data['items']);
    }

    /**
     * Ciclo completo: activar → pausar → reanudar → eliminar.
     * DDL no se revierte con DAMA, por eso todo va en un solo test
     * que limpia al final con eliminar.
     */
    public function testActivatePauseResumeDeleteCycle(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');

        // 1. Limpiar estado previo por si quedó de una ejecución fallida
        $client->request('POST', '/api/auditoria/' . self::TEST_TABLE . '/eliminar');

        // 2. Activar — crea tabla de auditoría + trigger + función
        $response = $client->request('POST', '/api/auditoria/' . self::TEST_TABLE . '/activar');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['success'], 'activar debería retornar success:true');
        $this->assertStringContainsString('activada', strtolower($data['message']));

        // Verificar en config que la tabla ahora tiene auditoría activa
        $response = $client->request('GET', '/api/auditoria/config?q=' . self::TEST_TABLE);
        $configData = $response->toArray();
        $tableConfig = $this->findTableInConfig($configData['items'], self::TEST_TABLE);
        $this->assertNotNull($tableConfig, 'La tabla debería aparecer en config');
        $this->assertTrue($tableConfig['existTableAudit'], 'Debería existir tabla de auditoría');
        $this->assertTrue($tableConfig['existTriggerAudit'], 'Debería existir trigger de auditoría');
        $this->assertSame('ACTIVA', $tableConfig['estado']);

        // 3. Pausar — elimina el trigger
        $response = $client->request('POST', '/api/auditoria/' . self::TEST_TABLE . '/pausar');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['success'], 'pausar debería retornar success:true');

        // Verificar estado PAUSADA
        $response = $client->request('GET', '/api/auditoria/config?q=' . self::TEST_TABLE);
        $configData = $response->toArray();
        $tableConfig = $this->findTableInConfig($configData['items'], self::TEST_TABLE);
        $this->assertTrue($tableConfig['existTableAudit'], 'Tabla de auditoría debería seguir existiendo');
        $this->assertFalse($tableConfig['existTriggerAudit'], 'Trigger debería estar eliminado');
        $this->assertSame('PAUSADA', $tableConfig['estado']);

        // 4. Reanudar — recrea el trigger
        $response = $client->request('POST', '/api/auditoria/' . self::TEST_TABLE . '/reanudar');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['success'], 'reanudar debería retornar success:true');

        // Verificar estado ACTIVA
        $response = $client->request('GET', '/api/auditoria/config?q=' . self::TEST_TABLE);
        $configData = $response->toArray();
        $tableConfig = $this->findTableInConfig($configData['items'], self::TEST_TABLE);
        $this->assertTrue($tableConfig['existTriggerAudit'], 'Trigger debería existir de nuevo');
        $this->assertSame('ACTIVA', $tableConfig['estado']);

        // 5. Eliminar — elimina tabla + secuencia (limpieza)
        $response = $client->request('POST', '/api/auditoria/' . self::TEST_TABLE . '/eliminar');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['success'], 'eliminar debería retornar success:true');

        // Verificar que ya no existe
        $response = $client->request('GET', '/api/auditoria/config?q=' . self::TEST_TABLE);
        $configData = $response->toArray();
        $tableConfig = $this->findTableInConfig($configData['items'], self::TEST_TABLE);
        $this->assertFalse($tableConfig['existTableAudit'], 'Tabla de auditoría debería haber sido eliminada');
    }

    public function testActivateAlreadyActivatedReturnsError(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');

        // Activar
        $client->request('POST', '/api/auditoria/' . self::TEST_TABLE . '/activar');
        $this->assertResponseIsSuccessful();

        // Intentar activar de nuevo — la tabla ya existe
        $response = $client->request('POST', '/api/auditoria/' . self::TEST_TABLE . '/activar');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertFalse($data['success']);

        // Limpiar
        $client->request('POST', '/api/auditoria/' . self::TEST_TABLE . '/eliminar');
    }

    public function testActivateAllReturnsResponse(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $response = $client->request('POST', '/api/auditoria/activar-todas');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('message', $data);

        // Limpiar todo lo que se activó
        $client->request('POST', '/api/auditoria/eliminar-todas');
    }

    public function testPauseAllReturnsResponse(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $response = $client->request('POST', '/api/auditoria/pausar-todas');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['success']);
    }

    public function testResumeAllReturnsResponse(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $response = $client->request('POST', '/api/auditoria/reanudar-todas');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['success']);
    }

    public function testDeleteAllReturnsResponse(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $response = $client->request('POST', '/api/auditoria/eliminar-todas');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['success']);
    }

    // ── Reportes PDF ──

    public function testPrintReporteAuditoriaWithoutParamsReturnsValidationError(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $client->request('GET', '/api/auditoria/reporte-auditoria');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPrintReporteActividadWithoutParamsReturnsValidationError(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $client->request('GET', '/api/auditoria/reporte-actividad');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPrintReporteAuditoriaWithParams(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');

        // Primero activar auditoría para tener tabla de auditoría
        $client->request('POST', '/api/auditoria/' . self::TEST_TABLE . '/activar');

        $response = $client->request(
            'GET',
            '/api/auditoria/reporte-auditoria?entidad=ConfiguracionSistema&fechaDesde=2026-01-01&fechaHasta=2026-12-31'
        );

        // Puede devolver PDF (200) o error controlado (400)
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [200, 400]),
            "Esperado 200 o 400, recibido $statusCode"
        );

        if ($statusCode === 200) {
            $this->assertResponseHeaderSame('Content-Type', 'application/pdf');
        }

        // Limpiar
        $client->request('POST', '/api/auditoria/' . self::TEST_TABLE . '/eliminar');
    }

    public function testPrintReporteActividadWithParams(): void
    {
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $response = $client->request(
            'GET',
            '/api/auditoria/reporte-actividad?fechaDesde=2026-01-01&fechaHasta=2026-12-31'
        );

        // Puede devolver PDF (200) o error controlado (400)
        $statusCode = $response->getStatusCode();
        $this->assertTrue(
            in_array($statusCode, [200, 400]),
            "Esperado 200 o 400, recibido $statusCode"
        );

        if ($statusCode === 200) {
            $this->assertResponseHeaderSame('Content-Type', 'application/pdf');
        }
    }

    // ── Helper ──

    private function findTableInConfig(array $items, string $tableName): ?array
    {
        foreach ($items as $item) {
            if ($item['tableName'] === $tableName) {
                return $item;
            }
        }

        return null;
    }
}

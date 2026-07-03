<?php

namespace App\Tests\Functional;

use App\Entity\Usuario;
use Doctrine\DBAL\Connection;

class LogoutTest extends AbstractApiTestCase
{
    private const JSON_HEADERS = ['headers' => ['Accept' => 'application/json']];

    /**
     * Test que verifica que el endpoint de logout responda exitosamente
     * aun si no hay autenticación, ya que es una API stateless.
     */
    public function testLogoutWithoutAuthReturnsSuccess(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/logout', self::JSON_HEADERS);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertArrayHasKey('message', $data);
        $this->assertSame('Sesión cerrada correctamente.', $data['message']);

        // Verificar que no se haya insertado un log sin usuario
        /** @var Connection $conn */
        $conn = static::getContainer()->get('doctrine')->getConnection();
        $schema = static::getContainer()->getParameter('app.audit_shemma_data_name') ?: 'public';
        $logoutLogs = $conn->fetchAllAssociative("SELECT * FROM {$schema}.usuario_log_accion WHERE accion = 'Logout'");
        $this->assertEmpty($logoutLogs);
    }

    /**
     * Test que verifica que el logout con un token JWT válido
     * registre correctamente el evento en la auditoría.
     */
    public function testLogoutWithAuthAuditsAction(): void
    {
        // 1. Obtener cliente autenticado como 'targetuser'
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');

        // 2. Realizar petición de logout
        $response = $client->request('POST', '/logout');

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertArrayHasKey('message', $data);
        $this->assertSame('Sesión cerrada correctamente.', $data['message']);

        // 3. Verificar que se haya insertado el log en base de datos para 'targetuser'
        /** @var Connection $conn */
        $conn = static::getContainer()->get('doctrine')->getConnection();
        $schema = static::getContainer()->getParameter('app.audit_shemma_data_name') ?: 'public';

        $userRow = $conn->fetchAssociative("SELECT id FROM {$schema}.usuario WHERE username = 'targetuser'");
        $this->assertNotEmpty($userRow);
        $userId = $userRow['id'];

        $logoutLogs = $conn->fetchAllAssociative(
            "SELECT * FROM {$schema}.usuario_log_accion WHERE usuario_id = ? AND accion = 'Logout'",
            [$userId]
        );

        $this->assertCount(1, $logoutLogs);
        $this->assertSame('Logout', $logoutLogs[0]['accion']);
        $this->assertSame($userId, (int) $logoutLogs[0]['usuario_id']);
    }
}

<?php

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use Doctrine\DBAL\Connection;

final class UsuarioTest extends AbstractApiTestCase
{
    private const JSON_HEADERS = ['headers' => ['Accept' => 'application/json']];

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $username): array
    {
        return [
            'username' => $username,
            'apellido' => 'Apellido',
            'nombre' => 'Nombre',
            'email' => $username.'@test.com',
            'presidenciaId' => null,
            'roles' => ['ROLE_CONSULTA_PRESIDENCIA'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createUser(Client $client, string $username): array
    {
        $response = $client->request('POST', '/api/usuarios', [
            'json' => $this->validPayload($username),
        ]);
        self::assertResponseStatusCodeSame(201);

        return $response->toArray();
    }

    private function connection(): Connection
    {
        return static::getContainer()->get('doctrine')->getConnection();
    }

    private function schema(): string
    {
        return static::getContainer()->getParameter('app.audit_shemma_data_name') ?: 'public';
    }

    /**
     * @param array<string|int, mixed> $payload
     */
    private function assertNoSensitiveFields(array $payload): void
    {
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                self::assertNotContains($key, [
                    'password',
                    'authCode',
                    'emailAuthCode',
                    'token',
                    'twoFactorCode',
                ]);
            }
            if (is_array($value)) {
                $this->assertNoSensitiveFields($value);
            }
        }
    }

    public function testEndpointsRequireAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/usuarios', self::JSON_HEADERS);
        self::assertResponseStatusCodeSame(401);

        $client->request('POST', '/api/usuarios/1/reset-password');
        self::assertResponseStatusCodeSame(401);
    }

    public function testRoleUserCannotAccessManagement(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('GET', '/api/usuarios');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanListViewCreateEditDeactivateAndResetPassword(): void
    {
        $client = $this->createAuthenticatedClient();

        $collection = $client->request('GET', '/api/usuarios?estado=activos&limit=2')->toArray();
        self::assertArrayHasKey('items', $collection);
        self::assertArrayHasKey('pagination', $collection);
        self::assertSame(1, (int) $collection['pagination']['page']);
        self::assertSame(2, (int) $collection['pagination']['limit']);
        $this->assertNoSensitiveFields($collection);

        $created = $this->createUser($client, 'admin_managed_user');
        self::assertSame('admin_managed_user', $created['username']);
        self::assertTrue($created['mailSent']);
        self::assertNull($created['fechaBaja']);
        $this->assertNoSensitiveFields($created);

        $detail = $client->request('GET', '/api/usuarios/'.$created['id'])->toArray();
        self::assertSame($created['id'], $detail['id']);
        $this->assertNoSensitiveFields($detail);

        $updated = $client->request('PATCH', '/api/usuarios/'.$created['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['apellido' => 'Actualizado', 'nombre' => null],
        ])->toArray();
        self::assertSame('Actualizado', $updated['apellido']);
        self::assertNull($updated['nombre']);
        $this->assertNoSensitiveFields($updated);

        $hashBefore = (string) $this->connection()->fetchOne(
            "SELECT password FROM {$this->schema()}.usuario WHERE id = ?",
            [$created['id']],
        );
        $reset = $client->request('POST', '/api/usuarios/'.$created['id'].'/reset-password')->toArray();
        self::assertTrue($reset['mailSent']);
        $hashAfter = (string) $this->connection()->fetchOne(
            "SELECT password FROM {$this->schema()}.usuario WHERE id = ?",
            [$created['id']],
        );
        self::assertNotSame($hashBefore, $hashAfter);
        $this->assertNoSensitiveFields($reset);

        $client->request('DELETE', '/api/usuarios/'.$created['id']);
        self::assertResponseStatusCodeSame(204);
        self::assertNotNull($this->connection()->fetchOne(
            "SELECT fecha_baja FROM {$this->schema()}.usuario WHERE id = ?",
            [$created['id']],
        ));
    }

    public function testAdminCannotUseSuperAdminActions(): void
    {
        $superAdmin = $this->createAuthenticatedClient('superadmin', 'superpass');
        $created = $this->createUser($superAdmin, 'super_actions_target');
        $superAdmin->request('DELETE', '/api/usuarios/'.$created['id']);

        $admin = $this->createAuthenticatedClient();
        $admin->request('POST', '/api/usuarios/'.$created['id'].'/reactivar');
        self::assertResponseStatusCodeSame(403);

        $admin->request('POST', '/api/usuarios/'.$created['id'].'/force-2fa');
        self::assertResponseStatusCodeSame(403);

        $admin->request('POST', '/api/usuarios/force-2fa-all');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCannotAssignOrModifySuperAdmin(): void
    {
        $admin = $this->createAuthenticatedClient();
        $payload = $this->validPayload('privilege_escalation');
        $payload['roles'] = ['ROLE_SUPER_ADMIN'];

        $admin->request('POST', '/api/usuarios', ['json' => $payload]);
        self::assertResponseStatusCodeSame(403);

        $superAdminId = (int) $this->connection()->fetchOne(
            "SELECT id FROM {$this->schema()}.usuario WHERE username = 'superadmin'",
        );
        $admin->request('PATCH', '/api/usuarios/'.$superAdminId, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['apellido' => 'Intento'],
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminCanReactivateAndForceTwoFactorWhenVersionIsNull(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $created = $this->createUser($client, 'two_factor_target');

        $client->request('DELETE', '/api/usuarios/'.$created['id']);
        $reactivated = $client->request('POST', '/api/usuarios/'.$created['id'].'/reactivar')->toArray();
        self::assertNull($reactivated['fechaBaja']);

        $this->connection()->executeStatement(
            "UPDATE {$this->schema()}.usuario SET trusted_version = NULL WHERE id = ?",
            [$created['id']],
        );
        $forced = $client->request('POST', '/api/usuarios/'.$created['id'].'/force-2fa')->toArray();
        self::assertSame(1, $forced['trustedVersion'], json_encode($forced, JSON_UNESCAPED_SLASHES));
        $this->assertNoSensitiveFields($forced);
    }

    public function testSuperAdminCanForceTwoFactorForAllInOneOperation(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $schema = $this->schema();
        $this->connection()->executeStatement("UPDATE {$schema}.usuario SET trusted_version = NULL");

        $client->request('POST', '/api/usuarios/force-2fa-all');
        self::assertResponseStatusCodeSame(204);

        $versions = $this->connection()->fetchFirstColumn("SELECT trusted_version FROM {$schema}.usuario");
        self::assertNotEmpty($versions);
        foreach ($versions as $version) {
            self::assertSame(1, (int) $version);
        }
    }

    public function testPaginationSearchStateFilterAndSorting(): void
    {
        $client = $this->createAuthenticatedClient();
        $active = $this->createUser($client, 'searchable_active');
        $inactive = $this->createUser($client, 'searchable_inactive');
        $client->request('DELETE', '/api/usuarios/'.$inactive['id']);

        $response = $client->request(
            'GET',
            '/api/usuarios?estado=activos&search=searchable&limit=1&page=1&sort=username&direction=desc',
        )->toArray();
        self::assertCount(1, $response['items']);
        self::assertSame($active['id'], $response['items'][0]['id']);
        self::assertSame(1, (int) $response['pagination']['totalItems']);
        self::assertSame(1, (int) $response['pagination']['totalPages']);

        $response = $client->request(
            'GET',
            '/api/usuarios?estado=baja&search=searchable',
        )->toArray();
        self::assertCount(1, $response['items']);
        self::assertSame($inactive['id'], $response['items'][0]['id']);

        $response = $client->request(
            'GET',
            '/api/usuarios?estado=todos&sort=username&direction=asc&limit=50',
        )->toArray();
        $usernames = array_column($response['items'], 'username');
        $sorted = $usernames;
        sort($sorted, SORT_STRING);
        self::assertSame($sorted, $usernames);
    }

    public function testDuplicateUsernameReturnsConflict(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/usuarios', [
            'json' => $this->validPayload('testuser'),
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testValidationErrorsAreStructuredAndRolesAreRestricted(): void
    {
        $client = $this->createAuthenticatedClient();
        $payload = $this->validPayload('');
        $payload['apellido'] = '';
        $payload['email'] = 'correo-invalido';
        $payload['roles'] = ['ROLE_ADMIN', 'ROLE_ADMIN', 'ROLE_NOT_ALLOWED'];

        $response = $client->request('POST', '/api/usuarios', ['json' => $payload]);
        self::assertResponseStatusCodeSame(422);
        $body = $response->toArray(false);
        self::assertSame('Los datos ingresados no son válidos.', $body['message']);
        self::assertArrayHasKey('username', $body['errors']);
        self::assertArrayHasKey('apellido', $body['errors']);
        self::assertArrayHasKey('email', $body['errors']);
        self::assertArrayHasKey('roles', $body['errors']);
    }

    public function testCreateSupportsOptionalNameAndPresidencyRelation(): void
    {
        $client = $this->createAuthenticatedClient();
        $presidencias = $client->request(
            'GET',
            '/api/presidencias?estado=A&itemsPerPage=1',
        )->toArray();
        $presidencia = $presidencias['items'][0];

        $payload = $this->validPayload('presidencia_user');
        $payload['nombre'] = null;
        $payload['presidenciaId'] = $presidencia['id'];

        $created = $client->request('POST', '/api/usuarios', ['json' => $payload])->toArray();
        self::assertNull($created['nombre']);
        self::assertSame($presidencia['id'], $created['presidencia']['id']);
        self::assertSame($presidencia['tribu'], $created['presidencia']['tribu']);
    }

    public function testProtectedFieldsCannotBeUpdated(): void
    {
        $client = $this->createAuthenticatedClient();
        $created = $this->createUser($client, 'protected_fields_user');
        $fechaAlta = $created['fechaAlta'];

        $client->request('PATCH', '/api/usuarios/'.$created['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'password' => 'plain-text',
                'fechaAlta' => '2000-01-01',
                'trustedVersion' => 999,
                'authCode' => 'secret',
            ],
        ]);
        self::assertResponseStatusCodeSame(400);

        $detail = $client->request('GET', '/api/usuarios/'.$created['id'])->toArray();
        self::assertSame(substr($fechaAlta, 0, 10), substr($detail['fechaAlta'], 0, 10));
        self::assertSame(0, $detail['trustedVersion']);
        $this->assertNoSensitiveFields($detail);
    }

    public function testUserCanStillChangeOwnPassword(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('POST', '/api/usuarios/cambiar-clave', [
            'json' => [
                'oldPassword' => 'targetpass',
                'newPassword' => 'newpass1234',
                'confirmPassword' => 'newpass1234',
            ],
        ]);

        self::assertResponseIsSuccessful();
    }
}

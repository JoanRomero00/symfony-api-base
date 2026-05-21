<?php

/**
 * Tests funcionales del sistema de suplantación de usuarios.
 */

namespace App\Tests\Functional;

class ImpersonationTest extends AbstractApiTestCase
{
    private const JSON_HEADERS = ['headers' => ['Accept' => 'application/json']];

    public function testImpersonateRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/impersonacion/iniciar/1', self::JSON_HEADERS);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testImpersonateForbiddenForNonSuperAdmin(): void
    {
        // Obtener un ID válido con superadmin primero
        $superClient = $this->createAuthenticatedClient('superadmin', 'superpass');
        $usuarios = $superClient->request('GET', '/api/usuarios')->toArray();
        $targetId = $usuarios['items'][0]['id'];

        // Intentar impersonar con ROLE_ADMIN (no tiene ROLE_ALLOWED_TO_SWITCH)
        $client = $this->createAuthenticatedClient('testuser', 'testpass');
        $client->request('POST', "/api/impersonacion/iniciar/$targetId");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testImpersonateIn(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');

        // Obtener ID del usuario target
        $usuarios = $client->request('GET', '/api/usuarios')->toArray();
        $targetId = null;
        foreach ($usuarios['items'] as $u) {
            if ($u['username'] === 'targetuser') {
                $targetId = $u['id'];
                break;
            }
        }
        $this->assertNotNull($targetId);

        $response = $client->request('POST', "/api/impersonacion/iniciar/$targetId");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testImpersonateOut(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');

        // Obtener ID del usuario target
        $usuarios = $client->request('GET', '/api/usuarios')->toArray();
        $targetId = null;
        foreach ($usuarios['items'] as $u) {
            if ($u['username'] === 'targetuser') {
                $targetId = $u['id'];
                break;
            }
        }

        // Impersonar
        $response = $client->request('POST', "/api/impersonacion/iniciar/$targetId");
        $impersonationToken = $response->toArray()['token'];

        // Salir de impersonación con el token obtenido
        $impersonatedClient = static::createClient([], [
            'headers' => [
                'Authorization' => 'Bearer ' . $impersonationToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $response = $impersonatedClient->request('POST', '/api/impersonacion/finalizar');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testImpersonateOutWithoutActiveImpersonation(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');

        $client->request('POST', '/api/impersonacion/finalizar');

        $this->assertResponseStatusCodeSame(400);
    }
}

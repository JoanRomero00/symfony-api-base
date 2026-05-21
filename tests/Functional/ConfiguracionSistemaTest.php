<?php

/**
 * Tests funcionales del CRUD de ConfiguracionSistema.
 */

namespace App\Tests\Functional;

class ConfiguracionSistemaTest extends AbstractApiTestCase
{
    private const JSON_HEADERS = ['headers' => ['Accept' => 'application/json']];

    public function testGetCollectionRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/configuracion_sistemas', self::JSON_HEADERS);

        $this->assertResponseStatusCodeSame(401);
    }

    // --- Tests 403: ROLE_USER no puede acceder ---

    public function testGetCollectionForbiddenForRoleUser(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('GET', '/api/configuracion_sistemas');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateForbiddenForRoleUser(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('POST', '/api/configuracion_sistemas', [
            'json' => ['clave' => 'forbidden', 'valor' => 'forbidden'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // --- Tests 403: ROLE_ADMIN no puede acceder ---

    public function testGetCollectionForbiddenForRoleAdmin(): void
    {
        $client = $this->createAuthenticatedClient(); // testuser = ROLE_ADMIN
        $client->request('GET', '/api/configuracion_sistemas');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateForbiddenForRoleAdmin(): void
    {
        $client = $this->createAuthenticatedClient(); // testuser = ROLE_ADMIN
        $client->request('POST', '/api/configuracion_sistemas', [
            'json' => ['clave' => 'forbidden', 'valor' => 'forbidden'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // --- Tests funcionales con ROLE_SUPER_ADMIN ---

    public function testGetCollection(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $response = $client->request('GET', '/api/configuracion_sistemas');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
    }

    public function testCreate(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $response = $client->request('POST', '/api/configuracion_sistemas', [
            'json' => [
                'clave' => 'test_key',
                'valor' => 'test_value',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('test_key', $data['clave']);
        $this->assertSame('test_value', $data['valor']);
    }

    public function testCreateValidationError(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $client->request('POST', '/api/configuracion_sistemas', [
            'json' => [
                'clave' => '',
                'valor' => '',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testGetSingle(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');

        $response = $client->request('POST', '/api/configuracion_sistemas', [
            'json' => ['clave' => 'get_key', 'valor' => 'get_value'],
        ]);
        $id = $response->toArray()['id'];

        $response = $client->request('GET', "/api/configuracion_sistemas/$id");

        $this->assertResponseIsSuccessful();
        $this->assertSame('get_key', $response->toArray()['clave']);
    }

    public function testUpdate(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');

        $response = $client->request('POST', '/api/configuracion_sistemas', [
            'json' => ['clave' => 'update_key', 'valor' => 'original'],
        ]);
        $id = $response->toArray()['id'];

        $response = $client->request('PATCH', "/api/configuracion_sistemas/$id", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['valor' => 'modificado'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('modificado', $response->toArray()['valor']);
    }

    public function testDelete(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');

        $response = $client->request('POST', '/api/configuracion_sistemas', [
            'json' => ['clave' => 'delete_key', 'valor' => 'delete_value'],
        ]);
        $id = $response->toArray()['id'];

        $client->request('DELETE', "/api/configuracion_sistemas/$id");

        $this->assertResponseStatusCodeSame(204);
    }
}

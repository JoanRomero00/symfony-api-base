<?php

/**
 * Tests de autenticación en endpoints públicos: verifica que X-API-Key es requerida y JWT no aplica.
 */

namespace App\Tests\Functional;

class PublicApiTest extends \App\Tests\AbstractApiTestCase
{
    private const API_KEY = 'test-api-key-123';

    private function createPublicClient(): \ApiPlatform\Symfony\Bundle\Test\Client
    {
        return static::createClient([], [
            'headers' => [
                'X-API-Key' => self::API_KEY,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    // Autenticación — X-API-Key requerida
    // ──────────────────────────────────────────────

    public function testRejectsRequestWithoutApiKey(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/publica/recursos', ['headers' => ['Accept' => 'application/json']]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRejectsRequestWithWrongApiKey(): void
    {
        $client = static::createClient([], [
            'headers' => [
                'X-API-Key' => 'wrong-key',
                'Accept' => 'application/json',
            ],
        ]);
        $client->request('GET', '/api/publica/recursos');

        $this->assertResponseStatusCodeSame(401);
    }

    // ──────────────────────────────────────────────
    // Verificar que JWT no funciona en endpoints públicos
    // ──────────────────────────────────────────────

    public function testJwtTokenDoesNotWorkOnPublicEndpoints(): void
    {
        // Autenticar con JWT
        $client = $this->createAuthenticatedClient();

        // Intentar acceder a endpoint público con JWT (sin X-API-Key)
        $client->request('GET', '/api/publica/recursos');

        // El firewall public_api no acepta JWT, solo X-API-Key
        $this->assertResponseStatusCodeSame(401);
    }
}

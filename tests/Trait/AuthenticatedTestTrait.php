<?php

/**
 * Trait reutilizable: obtiene un cliente HTTP autenticado con JWT para usar en tests funcionales.
 */

namespace App\Tests\Trait;

use ApiPlatform\Symfony\Bundle\Test\Client;

trait AuthenticatedTestTrait
{
    private ?string $token = null;

    protected function createAuthenticatedClient(
        string $username = 'testuser',
        string $password = 'testpass',
    ): Client {
        $client = static::createClient([], [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);

        $response = $client->request('POST', '/login', [
            'json' => [
                'username' => $username,
                'password' => $password,
            ],
        ]);

        $this->token = $response->toArray()['token'];

        return static::createClient([], [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}

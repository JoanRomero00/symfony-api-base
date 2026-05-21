<?php

/**
 * Tests del rate limiting en /login: verifica que el límite de 5 intentos dispare el 429.
 */

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;

/**
 * Tests del rate limiting en POST /login (issue #77).
 *
 * El firewall `login` usa `login_throttling` con 5 intentos/minuto. Tras superarlo
 * devuelve HTTP 429. El aislamiento del storage entre tests está en AbstractApiTestCase::setUp().
 */
class LoginRateLimitTest extends AbstractApiTestCase
{
    private const MAX_ATTEMPTS = 5;

    private function attemptLogin(Client $client, string $username, string $password): int
    {
        $client->request('POST', '/login', [
            'json' => ['username' => $username, 'password' => $password],
        ]);

        return $client->getResponse()->getStatusCode();
    }

    public function testSuccessfulLoginReturns200(): void
    {
        $client = static::createClient();
        $this->assertSame(200, $this->attemptLogin($client, 'testuser', 'testpass'));
    }

    public function testFailedLoginReturns401(): void
    {
        $client = static::createClient();
        $this->assertSame(401, $this->attemptLogin($client, 'testuser', 'wrongpass'));
    }

    public function testFifthFailedAttemptStillReturns401(): void
    {
        $client = static::createClient();

        for ($i = 1; $i <= self::MAX_ATTEMPTS; $i++) {
            $status = $this->attemptLogin($client, 'testuser', 'wrongpass');
            $this->assertSame(401, $status, "Intento #$i debería devolver 401, devolvió $status");
        }
    }

    public function testSixthFailedAttemptReturns429(): void
    {
        $client = static::createClient();

        for ($i = 1; $i <= self::MAX_ATTEMPTS; $i++) {
            $this->attemptLogin($client, 'testuser', 'wrongpass');
        }

        $this->assertSame(429, $this->attemptLogin($client, 'testuser', 'wrongpass'));
    }

    public function test429ResponseIncludesRetryAfterHeader(): void
    {
        $client = static::createClient();

        for ($i = 1; $i <= self::MAX_ATTEMPTS + 1; $i++) {
            $this->attemptLogin($client, 'testuser', 'wrongpass');
        }

        $response = $client->getResponse();
        $this->assertSame(429, $response->getStatusCode());
        $this->assertNotEmpty(
            $response->getHeaders(false)['retry-after'] ?? [],
            'La respuesta 429 debe incluir el header Retry-After'
        );
    }

    public function test429BlocksEvenWithCorrectPassword(): void
    {
        $client = static::createClient();

        // Quemar el límite con intentos fallidos
        for ($i = 1; $i <= self::MAX_ATTEMPTS + 1; $i++) {
            $this->attemptLogin($client, 'testuser', 'wrongpass');
        }

        // Aunque el password sea correcto, el throttle debe seguir activo
        $this->assertSame(429, $this->attemptLogin($client, 'testuser', 'testpass'));
    }

    public function testFailedAttemptsPersistAcrossSuccessfulLogin(): void
    {
        $client = static::createClient();

        // 4 intentos fallidos (todavía bajo el límite)
        for ($i = 1; $i <= self::MAX_ATTEMPTS - 1; $i++) {
            $this->assertSame(401, $this->attemptLogin($client, 'testuser', 'wrongpass'));
        }

        // Login exitoso intercalado: NO resetea el contador en limiters peekable
        // (Symfony usa fixed_window — la ventana solo expira por tiempo).
        $this->assertSame(200, $this->attemptLogin($client, 'testuser', 'testpass'));

        // El 5° y 6° fallo consecutivo siguen acumulando: 5° aún 401, 6° dispara 429.
        $this->assertSame(401, $this->attemptLogin($client, 'testuser', 'wrongpass'));
        $this->assertSame(429, $this->attemptLogin($client, 'testuser', 'wrongpass'));
    }

    public function testThrottlingIsScopedByUsername(): void
    {
        $client = static::createClient();

        // Quemar el límite de 'testuser'
        for ($i = 1; $i <= self::MAX_ATTEMPTS + 1; $i++) {
            $this->attemptLogin($client, 'testuser', 'wrongpass');
        }

        $this->assertSame(429, $this->attemptLogin($client, 'testuser', 'wrongpass'));

        // Otro usuario desde la misma IP debe poder loguearse normalmente
        $this->assertSame(200, $this->attemptLogin($client, 'superadmin', 'superpass'));
    }
}

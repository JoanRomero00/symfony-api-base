<?php

/**
 * Tests funcionales de la entidad Usuario: CRUD, roles, hashing, baja lógica, reset y change password.
 */

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;

class UsuarioTest extends AbstractApiTestCase
{
    private const JSON_HEADERS = ['headers' => ['Accept' => 'application/json']];

    private function fetchPasswordHash(string $username): string
    {
        /** @var Connection $conn */
        $conn = static::getContainer()->get('doctrine')->getConnection();

        $schema = static::getContainer()->getParameter('app.audit_shemma_data_name') ?: 'public';

        return (string) $conn->fetchOne("SELECT password FROM {$schema}.usuario WHERE username = ?", [$username]);
    }

    // --- Tests 401: requiere autenticación ---

    public function testGetCollectionRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/usuarios', self::JSON_HEADERS);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testResetPasswordRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('PATCH', '/api/usuarios/1/restablecer-clave', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // --- Tests 403: ROLE_USER no puede acceder a usuarios ---

    public function testGetCollectionForbiddenForRoleUser(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('GET', '/api/usuarios');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetSingleForbiddenForRoleUser(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $usuarios = $client->request('GET', '/api/usuarios')->toArray();
        $id = $usuarios['items'][0]['id'];

        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('GET', "/api/usuarios/$id");

        $this->assertResponseStatusCodeSame(403);
    }

    // --- Tests 403: ROLE_ADMIN no puede crear/modificar/eliminar usuarios ---

    public function testCreateForbiddenForRoleAdmin(): void
    {
        $client = $this->createAuthenticatedClient(); // testuser = ROLE_ADMIN
        $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'forbidden_user',
                'password' => 'pass1234',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Forbidden',
                'nombre' => 'User',
                'email' => 'forbidden@test.com',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testPatchForbiddenForRoleAdmin(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $usuarios = $client->request('GET', '/api/usuarios')->toArray();
        $id = $usuarios['items'][0]['id'];

        $client = $this->createAuthenticatedClient(); // testuser = ROLE_ADMIN
        $client->request('PATCH', "/api/usuarios/$id", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['apellido' => 'Forbidden'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testBajaForbiddenForRoleAdmin(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $usuarios = $client->request('GET', '/api/usuarios')->toArray();
        $id = $usuarios['items'][0]['id'];

        $client = $this->createAuthenticatedClient(); // testuser = ROLE_ADMIN
        $client->request('PATCH', "/api/usuarios/$id/dar-de-baja", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testReactivarForbiddenForRoleAdmin(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $usuarios = $client->request('GET', '/api/usuarios')->toArray();
        $id = $usuarios['items'][0]['id'];

        $client = $this->createAuthenticatedClient(); // testuser = ROLE_ADMIN
        $client->request('PATCH', "/api/usuarios/$id/reactivar", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testResetPasswordForbiddenForRoleAdmin(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $usuarios = $client->request('GET', '/api/usuarios')->toArray();
        $id = $usuarios['items'][0]['id'];

        $client = $this->createAuthenticatedClient(); // testuser = ROLE_ADMIN
        $client->request('PATCH', "/api/usuarios/$id/restablecer-clave", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // --- Tests 200: ROLE_ADMIN puede leer usuarios ---

    public function testGetCollection(): void
    {
        $client = $this->createAuthenticatedClient(); // testuser = ROLE_ADMIN
        $response = $client->request('GET', '/api/usuarios');

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('links', $data);
        $this->assertNotEmpty($data['items']);

        $usuario = $data['items'][0];
        $this->assertArrayHasKey('id', $usuario);
        $this->assertArrayHasKey('username', $usuario);
        $this->assertArrayHasKey('roles', $usuario);
        $this->assertArrayHasKey('apellido', $usuario);
        $this->assertArrayHasKey('nombre', $usuario);
        $this->assertArrayHasKey('email', $usuario);
        $this->assertArrayHasKey('fechaAlta', $usuario);
    }

    public function testGetSingle(): void
    {
        $client = $this->createAuthenticatedClient(); // testuser = ROLE_ADMIN
        $usuarios = $client->request('GET', '/api/usuarios')->toArray();
        $id = $usuarios['items'][0]['id'];

        $response = $client->request('GET', "/api/usuarios/$id");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('username', $data);
        $this->assertArrayNotHasKey('password', $data);
    }

    // --- Tests CRUD: ROLE_SUPER_ADMIN puede gestionar usuarios ---

    public function testCreate(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $response = $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'newuser',
                'password' => 'newpass123',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Nuevo',
                'nombre' => 'Usuario',
                'email' => 'nuevo@test.com',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('newuser', $data['username']);
        $this->assertSame('Nuevo', $data['apellido']);
        $this->assertArrayNotHasKey('password', $data);
    }

    public function testCreateValidationError(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => '',
                'password' => '',
                'roles' => [],
                'apellido' => '',
                'nombre' => '',
                'email' => '',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCreateDuplicateUsername(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'testuser', // ya existe en fixtures
                'password' => 'otropass',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Dup',
                'nombre' => 'User',
                'email' => 'dup@test.com',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdate(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');

        $response = $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'updateuser',
                'password' => 'pass1234',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Original',
                'nombre' => 'User',
                'email' => 'update@test.com',
            ],
        ]);
        $id = $response->toArray()['id'];

        $response = $client->request('PATCH', "/api/usuarios/$id", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['apellido' => 'Modificado'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('Modificado', $data['apellido']);
        $this->assertArrayNotHasKey('password', $data);
    }

    public function testBajaLogica(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');

        $response = $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'bajauser',
                'password' => 'pass1234',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Baja',
                'nombre' => 'User',
                'email' => 'baja@test.com',
            ],
        ]);
        $id = $response->toArray()['id'];

        $response = $client->request('PATCH', "/api/usuarios/$id/dar-de-baja", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertNotNull($response->toArray()['fechaBaja']);
    }

    public function testReactivar(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');

        $response = $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'reactivaruser',
                'password' => 'pass1234',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Reactivar',
                'nombre' => 'User',
                'email' => 'reactivar@test.com',
            ],
        ]);
        $id = $response->toArray()['id'];

        // Dar de baja primero
        $client->request('PATCH', "/api/usuarios/$id/dar-de-baja", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Reactivar
        $response = $client->request('PATCH', "/api/usuarios/$id/reactivar", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertNull($response->toArray()['fechaBaja']);
    }

    public function testResetPassword(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');

        // Crear usuario para resetear
        $response = $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'resetuser',
                'password' => 'pass1234',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Reset',
                'nombre' => 'User',
                'email' => 'reset@test.com',
            ],
        ]);
        $id = $response->toArray()['id'];

        $client->request('PATCH', "/api/usuarios/$id/restablecer-clave", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
    }

    // --- Tests ChangePassword: ROLE_USER puede cambiar su propia clave ---

    public function testChangePasswordAsRoleUser(): void
    {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');

        $client->request('POST', '/api/usuarios/cambiar-clave', [
            'json' => [
                'oldPassword' => 'targetpass',
                'newPassword' => 'newpass1234',
                'confirmPassword' => 'newpass1234',
            ],
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testChangePassword(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/usuarios/cambiar-clave', [
            'json' => [
                'oldPassword' => 'testpass',
                'newPassword' => 'newpass1234',
                'confirmPassword' => 'newpass1234',
            ],
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testChangePasswordWrongOldPassword(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/usuarios/cambiar-clave', [
            'json' => [
                'oldPassword' => 'wrongpass',
                'newPassword' => 'newpass1234',
                'confirmPassword' => 'newpass1234',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangePasswordMismatch(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/usuarios/cambiar-clave', [
            'json' => [
                'oldPassword' => 'testpass',
                'newPassword' => 'newpass1234',
                'confirmPassword' => 'diferente1234',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    // --- Tests de hashing y persistencia de password ---

    public function testCreateHashesPasswordAndAllowsLogin(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'hash_create_user',
                'password' => 'plainpass1234',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Hash',
                'nombre' => 'Create',
                'email' => 'hashcreate@test.com',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Si el password se guardara en claro, el hasher fallaría al validar y el login devolvería 401
        $loginClient = static::createClient();
        $loginClient->request('POST', '/login', [
            'json' => ['username' => 'hash_create_user', 'password' => 'plainpass1234'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $loginClient->getResponse()->toArray());
    }

    public function testUpdateWithPasswordHashesAndAllowsLogin(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $response = $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'hash_update_user',
                'password' => 'origpass1234',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Hash',
                'nombre' => 'Update',
                'email' => 'hashupdate@test.com',
            ],
        ]);
        $id = $response->toArray()['id'];

        $client->request('PATCH', "/api/usuarios/$id", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['password' => 'newpass1234'],
        ]);
        $this->assertResponseIsSuccessful();

        $loginClient = static::createClient();
        $loginClient->request('POST', '/login', [
            'json' => ['username' => 'hash_update_user', 'password' => 'newpass1234'],
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function testUpdateWithoutPasswordPreservesHashAndLogin(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $response = $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'hash_preserve_user',
                'password' => 'keep1234pass',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Hash',
                'nombre' => 'Preserve',
                'email' => 'hashpreserve@test.com',
            ],
        ]);
        $id = $response->toArray()['id'];

        $hashBefore = $this->fetchPasswordHash('hash_preserve_user');

        $client->request('PATCH', "/api/usuarios/$id", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['apellido' => 'Modificado'],
        ]);
        $this->assertResponseIsSuccessful();

        // El hash no debe ser re-hasheado cuando no se envía password en el PATCH
        $this->assertSame($hashBefore, $this->fetchPasswordHash('hash_preserve_user'));

        $loginClient = static::createClient();
        $loginClient->request('POST', '/login', [
            'json' => ['username' => 'hash_preserve_user', 'password' => 'keep1234pass'],
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function testResetPasswordPersistsNewHash(): void
    {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $response = $client->request('POST', '/api/usuarios', [
            'json' => [
                'username' => 'hash_reset_user',
                'password' => 'origpass1234',
                'roles' => ['ROLE_USER'],
                'apellido' => 'Hash',
                'nombre' => 'Reset',
                'email' => 'hashreset@test.com',
            ],
        ]);
        $id = $response->toArray()['id'];

        $hashBefore = $this->fetchPasswordHash('hash_reset_user');

        $client->request('PATCH', "/api/usuarios/$id/restablecer-clave", [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        $this->assertResponseIsSuccessful();

        // Sin $em->flush() en el processor, el hash en DB no cambiaría y este assert fallaría
        $this->assertNotSame($hashBefore, $this->fetchPasswordHash('hash_reset_user'));
    }

    public function testChangePasswordAllowsLoginWithNewPasswordAndRejectsOld(): void
    {
        // targetuser tiene clave 'targetpass' en fixtures
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('POST', '/api/usuarios/cambiar-clave', [
            'json' => [
                'oldPassword' => 'targetpass',
                'newPassword' => 'brandnew1234',
                'confirmPassword' => 'brandnew1234',
            ],
        ]);
        $this->assertResponseIsSuccessful();

        $loginClient = static::createClient();
        $loginClient->request('POST', '/login', [
            'json' => ['username' => 'targetuser', 'password' => 'brandnew1234'],
        ]);
        $this->assertResponseIsSuccessful();

        $loginClient = static::createClient();
        $loginClient->request('POST', '/login', [
            'json' => ['username' => 'targetuser', 'password' => 'targetpass'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }
}

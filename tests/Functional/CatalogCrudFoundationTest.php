<?php

/**
 * Verifica el contrato común de seguridad, validación y activación de los catálogos.
 */

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;

class CatalogCrudFoundationTest extends AbstractApiTestCase
{
    /**
     * @return iterable<string, array{
     *     string,
     *     array<string, mixed>,
     *     array<string, mixed>,
     *     array<string, mixed>,
     *     string,
     *     mixed
     * }>
     */
    public static function catalogProvider(): iterable
    {
        yield 'fuero' => [
            '/api/fueros',
            [
                'descripcion' => 'Fuero de prueba funcional',
                'codFuero' => 'FP',
            ],
            [
                'descripcion' => '',
                'codFuero' => 'LARGO',
            ],
            ['descripcion' => 'Fuero actualizado'],
            'descripcion',
            'Fuero actualizado',
        ];

        yield 'tipo de marca' => [
            '/api/tipo_marcas',
            [
                'descripcion' => 'Marca de prueba funcional',
                'letra' => 'Z',
            ],
            [
                'descripcion' => '',
                'letra' => 'ZZ',
            ],
            ['descripcion' => 'Marca actualizada'],
            'descripcion',
            'Marca actualizada',
        ];

        yield 'presidencia' => [
            '/api/presidencias',
            [
                'tribu' => 'Presidencia de prueba funcional',
                'cantSala' => 2,
                'vocSala' => 3,
                'cantSalaPro' => 1,
                'fecInst' => '2020-01-15',
                'idInst' => 12345,
                // El sistema anterior admite presidencias sin correo configurado.
                'email' => '',
                'codOrg' => 100,
                'licencia' => 1,
                'sortComun' => 1,
                'sortAdHoc' => 0,
                'sortCinco' => 1,
                'sortComp' => 0,
                'codFuero' => 'PF',
                'vocOtroFuero' => 1,
                'resta' => 0,
                'sorteoAleatorio' => 1,
            ],
            [
                'tribu' => '',
                'cantSala' => 100,
                'vocSala' => 100,
                'cantSalaPro' => 100,
                'fecInst' => '2020-01-15',
                'idInst' => 1000000000,
                'email' => 'correo-invalido',
                'codOrg' => 100,
                'codFuero' => 'LARGO',
            ],
            ['tribu' => 'Presidencia actualizada'],
            'tribu',
            'Presidencia actualizada',
        ];
    }

    #[DataProvider('catalogProvider')]
    public function testCollectionRequiresAuthentication(
        string $resourcePath,
        array $validPayload,
        array $invalidPayload,
        array $updatePayload,
        string $updatedField,
        mixed $updatedValue,
    ): void {
        $client = static::createClient();
        $client->request('GET', $resourcePath, [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    #[DataProvider('catalogProvider')]
    public function testRoleUserCannotAccessCatalog(
        string $resourcePath,
        array $validPayload,
        array $invalidPayload,
        array $updatePayload,
        string $updatedField,
        mixed $updatedValue,
    ): void {
        $client = $this->createAuthenticatedClient('targetuser', 'targetpass');
        $client->request('GET', $resourcePath);

        $this->assertResponseStatusCodeSame(403);
    }

    #[DataProvider('catalogProvider')]
    public function testRoleAdminCanCreateAndEditButCannotWriteState(
        string $resourcePath,
        array $validPayload,
        array $invalidPayload,
        array $updatePayload,
        string $updatedField,
        mixed $updatedValue,
    ): void {
        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', $resourcePath, [
            'json' => [...$validPayload, 'estado' => 'B'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('A', $data['estado']);
        $this->assertIsInt($data['lastUserAppId']);

        $response = $client->request('PATCH', sprintf('%s/%d', $resourcePath, $data['id']), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [...$updatePayload, 'estado' => 'B'],
        ]);

        $this->assertResponseIsSuccessful();
        $updatedData = $response->toArray();
        $this->assertSame($updatedValue, $updatedData[$updatedField]);
        $this->assertSame('A', $updatedData['estado']);
    }

    #[DataProvider('catalogProvider')]
    public function testInvalidPayloadReturnsValidationError(
        string $resourcePath,
        array $validPayload,
        array $invalidPayload,
        array $updatePayload,
        string $updatedField,
        mixed $updatedValue,
    ): void {
        $client = $this->createAuthenticatedClient();
        $client->request('POST', $resourcePath, [
            'json' => $invalidPayload,
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    #[DataProvider('catalogProvider')]
    public function testRoleAdminCannotChangeActivationState(
        string $resourcePath,
        array $validPayload,
        array $invalidPayload,
        array $updatePayload,
        string $updatedField,
        mixed $updatedValue,
    ): void {
        $client = $this->createAuthenticatedClient();
        $id = $this->createResource($client, $resourcePath, $validPayload);

        $client->request('PATCH', sprintf('%s/%d/deactivate', $resourcePath, $id), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(403);

        $client->request('PATCH', sprintf('%s/%d/activate', $resourcePath, $id), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    #[DataProvider('catalogProvider')]
    public function testSuperAdminCanDeactivateAndActivateIdempotently(
        string $resourcePath,
        array $validPayload,
        array $invalidPayload,
        array $updatePayload,
        string $updatedField,
        mixed $updatedValue,
    ): void {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $id = $this->createResource($client, $resourcePath, $validPayload);
        $deactivatePath = sprintf('%s/%d/deactivate', $resourcePath, $id);
        $activatePath = sprintf('%s/%d/activate', $resourcePath, $id);

        $response = $this->patchWithoutInput($client, $deactivatePath);
        $this->assertResponseIsSuccessful();
        $this->assertSame('B', $response->toArray()['estado']);

        $response = $this->patchWithoutInput($client, $deactivatePath);
        $this->assertResponseIsSuccessful();
        $this->assertSame('B', $response->toArray()['estado']);

        $response = $this->patchWithoutInput($client, $activatePath);
        $this->assertResponseIsSuccessful();
        $this->assertSame('A', $response->toArray()['estado']);

        $response = $this->patchWithoutInput($client, $activatePath);
        $this->assertResponseIsSuccessful();
        $this->assertSame('A', $response->toArray()['estado']);
    }

    #[DataProvider('catalogProvider')]
    public function testPhysicalDeleteIsNotAvailable(
        string $resourcePath,
        array $validPayload,
        array $invalidPayload,
        array $updatePayload,
        string $updatedField,
        mixed $updatedValue,
    ): void {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $id = $this->createResource($client, $resourcePath, $validPayload);

        $client->request('DELETE', sprintf('%s/%d', $resourcePath, $id));

        $this->assertResponseStatusCodeSame(405);
    }

    #[DataProvider('catalogProvider')]
    public function testCollectionSupportsGlobalSearchAndStateFilter(
        string $resourcePath,
        array $validPayload,
        array $invalidPayload,
        array $updatePayload,
        string $updatedField,
        mixed $updatedValue,
    ): void {
        $client = $this->createAuthenticatedClient('superadmin', 'superpass');
        $id = $this->createResource($client, $resourcePath, $validPayload);
        $this->patchWithoutInput($client, sprintf('%s/%d/deactivate', $resourcePath, $id));

        $response = $client->request('GET', $resourcePath, [
            'query' => [
                'q' => (string) array_values($validPayload)[0],
                'estado' => 'B',
                'itemsPerPage' => 1,
                'order' => ['id' => 'DESC'],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(1.0, $data['meta']['totalItems']);
        $this->assertCount(1, $data['items']);
        $this->assertSame('B', $data['items'][0]['estado']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createResource(Client $client, string $resourcePath, array $payload): int
    {
        $response = $client->request('POST', $resourcePath, [
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(201);

        return $response->toArray()['id'];
    }

    private function patchWithoutInput(Client $client, string $path): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $client->request('PATCH', $path, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [],
        ]);
    }
}

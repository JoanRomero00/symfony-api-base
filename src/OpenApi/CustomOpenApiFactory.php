<?php

/**
 * Decora el factory de OpenAPI para reflejar la estructura personalizada de respuestas paginadas.
 */

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * CustomOpenApiFactory.
 *
 * (SOLO MODIFICA CÓMO SE ARMA LA DOCUMENTACION DE OPENAPI: http://localhost:8000/api/docs.jsonopenapi QUE LUEGO EL FRONT-END TOMA PARA LA GENERACIÓN AUTOMÁTICA DE UN CLIENTE TIPADO, PARA HACER LLAMADAS A LA API.
 *
 * NO TIENE NINGUN EFECTO EN LA API)
 *
 * Decorador del esquema de la documentación OpenAPI de API Platform.
 * Modifica las respuestas de colección (GET /resources) para reflejar:
 * 1) la estructura de paginación personalizada: items/meta/links.
 * 2) fuerza que todos los schemas con id lo tengan como required + agrega 'iri' (inyectado por IriNormalizer en runtime).
 * 3) añade un esquema genérico reutilizable "PaginatedResponse"
 * 4) corrige bug de api-platform/doctrine-common v4.2.20+: genera parámetros de filtro array (ej: "curso[]") con style:deepObject, que es inválido en OpenAPI 3.x para arrays. Los normaliza a style:form + explode:true.
 */
final class CustomOpenApiFactory implements OpenApiFactoryInterface
{
    private const VERSION_UNAVAILABLE = 'desconocida (tag de git no disponible)';

    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        // Sobrescribimos info.version con la versión derivada de git
        // (tag + commits desde tag, ej: "1.0.0-3-gabc1234"). Si no hay
        // repositorio git o no hay tags, usamos un valor explícito que
        // deja claro en la documentación que la versión no se pudo leer.
        $info = $openApi->getInfo();
        $openApi = $openApi->withInfo($info->withVersion($this->resolveGitVersion()));

        // Obtener componentes y schemas
        $components = $openApi->getComponents();
        $schemas = $components?->getSchemas() ?? new \ArrayObject();

        // --- Definimos esquema 'PaginationMeta' ---
        if (!isset($schemas['PaginationMeta'])) {
            $schemas['PaginationMeta'] = new \ArrayObject([
                'type' => 'object',
                'properties' => [
                    'totalItems' => ['type' => 'integer', 'example' => 50],
                    'itemCount' => ['type' => 'integer', 'example' => 10],
                    'itemsPerPage' => ['type' => 'integer', 'example' => 10],
                    'currentPage' => ['type' => 'integer', 'example' => 1],
                    'totalPages' => ['type' => 'integer', 'example' => 5],
                ],
                'required' => ['totalItems', 'itemCount', 'itemsPerPage', 'currentPage', 'totalPages'],
            ]);
        }

        // --- Definimos esquema 'PaginationLinks': solo con Query Params ---
        if (!isset($schemas['PaginationLinks'])) {
            $schemas['PaginationLinks'] = new \ArrayObject([
                'type' => 'object',
                'properties' => [
                    'self' => ['type' => 'string', 'example' => 'page=1&itemsPerPage=10'],
                    'first' => ['type' => 'string', 'example' => 'page=1&itemsPerPage=10'],
                    'last' => ['type' => 'string', 'example' => 'page=5&itemsPerPage=10'],
                    'prev' => ['type' => 'string', 'example' => 'page=1&itemsPerPage=10'],
                    'next' => ['type' => 'string', 'example' => 'page=2&itemsPerPage=10'],
                ],
                'required' => ['self', 'first', 'last'],
            ]);
        }

        // --- Esquema genérico  reutilizable de colecciones 'PaginatedResponse' ---
        if (!isset($schemas['PaginatedResponse'])) {
            $schemas['PaginatedResponse'] = new \ArrayObject([
                'type' => 'object',
                'properties' => [
                    'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                    'links' => ['$ref' => '#/components/schemas/PaginationLinks'],
                ],
                'required' => ['meta', 'links'],
            ]);
        }

        // --- Ajustamos las respuestas de las operaciones GET de colección ---
        $paths = $openApi->getPaths();

        foreach ($paths->getPaths() as $path => $pathItem) {
            $getOperation = $pathItem->getGet();

            if (!$getOperation) {
                continue;
            }

            // Detectamos si es una colección, tiene parámetro page
            $isCollection = false;
            foreach ($getOperation->getParameters() as $parameter) {
                if ($parameter->getName() === 'page') {
                    $isCollection = true;
                    break;
                }
            }

            if (!$isCollection) {
                continue;
            }

            $responses = $getOperation->getResponses();

            if (!isset($responses['200'])) {
                continue;
            }

            $response200 = $responses['200'];
            $content = $response200->getContent();

            if (!isset($content['application/json'])) {
                continue;
            }

            $mediaType = $content['application/json'];
            $originalSchema = $mediaType->getSchema();

            if (!$originalSchema) {
                continue;
            }

            // Detectar referencia original del item
            $itemRef = null;

            if (isset($originalSchema['items']['$ref'])) {
                $itemRef = $originalSchema['items']['$ref'];
            } elseif (isset($originalSchema['items']) && is_array($originalSchema['items'])) {
                $itemRef = $originalSchema['items']['$ref'] ?? null;
            }

            // Nuevo esquema: items + iri: Agregamos ITEMS al esquema genérico con su referencia (itemRef) para correcto tipado
            $wrappedSchema = new \ArrayObject([
                'allOf' => [
                    ['$ref' => '#/components/schemas/PaginatedResponse'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'items' => $itemRef
                                    ? ['$ref' => $itemRef]
                                    : ['type' => 'object'],
                            ],
                        ],
                        'required' => ['items'],
                    ],
                ],
            ]);

            $newMediaType = new MediaType(schema: $wrappedSchema);
            $newContent = new \ArrayObject($content);
            $newContent['application/json'] = $newMediaType;

            // Creamos la nueva respuesta:
            $newResponse200 = new Response(
                description: $response200->getDescription() ?? 'Successful response',
                content: $newContent
            );

            $newResponses = $responses;
            $newResponses['200'] = $newResponse200;

            $newOperation = $getOperation->withResponses($newResponses);
            $newPathItem = $pathItem->withGet($newOperation);

            $paths->addPath($path, $newPathItem);
        }

        // --- Corregir style 'deepObject' → 'form' en parámetros de filtro array ---
        //
        // CONTEXTO DEL BUG:
        // A partir de api-platform/doctrine-common v4.2.20 (PR #7658), el trait
        // OpenApiFilterTrait::getOpenApiParameters() genera parámetros de filtro
        // array con style:'deepObject' y nombre con sufijo '[]' (ej: "curso[]").
        // Esto afecta a todos los filtros que usan ese trait: ExactFilter,
        // IriFilter, OrFilter y PartialSearchFilter.
        //
        // POR QUÉ ES INCORRECTO:
        // Según la spec OpenAPI 3.x, 'deepObject' es exclusivo de parámetros tipo
        // objeto (para serializar propiedades anidadas: param[key]=value).
        // Para arrays, el style válido es 'form' (el default para query params).
        // Consecuencia: el generador @hey-api/openapi-ts rechaza 'deepObject' como
        // ArrayStyle y produce un error de tipos en el cliente TypeScript generado.
        //
        // LA CORRECCIÓN:
        // Para un parámetro array con nombre "curso[]" y style:'form' + explode:true,
        // la serialización produce: curso[]=valor1&curso[]=valor2
        // Que es exactamente la bracket notation que PHP/Symfony espera.
        // El comportamiento en runtime es idéntico — solo corregimos el spec.
        //
        // CONDICIÓN DE APLICACIÓN (doble chequeo para evitar falsos positivos):
        // - style === 'deepObject': identifica parámetros afectados por el bug
        // - nombre termina en '[]': confirma que es un parámetro array de filtro
        //   y no un parámetro objeto legítimo que pudiera usar deepObject en el futuro
        foreach ($paths->getPaths() as $path => $pathItem) {
            $pathModified = false;

            // Mapeamos cada método HTTP a sus getters/setters en PathItem.
            // Solo GET tendrá filtros en la práctica, pero iteramos todos
            // para que el fix sea robusto ante futuros cambios en la API.
            $methods = [
                'getGet' => 'withGet',
                'getPost' => 'withPost',
                'getPatch' => 'withPatch',
                'getPut' => 'withPut',
                'getDelete' => 'withDelete',
            ];

            foreach ($methods as $getter => $withMethod) {
                $operation = $pathItem->$getter();

                if (!$operation || !$operation->getParameters()) {
                    continue;
                }

                $newParams = [];
                $operationModified = false;

                foreach ($operation->getParameters() as $param) {
                    if ('deepObject' === $param->getStyle() && str_ends_with($param->getName(), '[]')) {
                        // Reemplazamos deepObject (inválido para arrays en OpenAPI 3.x)
                        // por form + explode:true, que produce la bracket notation correcta.
                        $param = $param->withStyle('form')->withExplode(true);
                        $operationModified = true;
                    }
                    $newParams[] = $param;
                }

                if ($operationModified) {
                    $pathItem = $pathItem->$withMethod($operation->withParameters($newParams));
                    $pathModified = true;
                }
            }

            if ($pathModified) {
                $paths->addPath($path, $pathItem);
            }
        }

        // Forzamos id e iri como required en todo schema que tenga id (schemas de lectura de ApiResource)
        foreach ($schemas as $schemaName => $schema) {
            if (isset($schema['properties']['id'])) {
                $required = $schema['required'] ?? [];
                if (!in_array('id', $required, true)) {
                    $required[] = 'id';
                }

                // Agregar iri a properties (IriNormalizer lo inyecta en runtime, no es required porque puede fallar en recursos sin identificador)
                $schema['properties']['iri'] = [
                    'type' => 'string',
                    'format' => 'uri-reference',
                    'readOnly' => true,
                    'example' => '/api/recurso/1',
                ];

                $schema['required'] = $required;
                $schemas[$schemaName] = $schema;
            }
        }

        $newComponents = $components
            ? $components->withSchemas($schemas)
            : null;

        return $newComponents ? $openApi->withComponents($newComponents)
            : $openApi;
    }

    /**
     * Devuelve la versión derivada de git: última tag más cantidad de commits
     * y hash corto si hay commits posteriores (ej: "1.0.0" o "1.0.0-3-gabc1234").
     * Si git no está disponible o no hay tags, devuelve un literal explícito.
     */
    private function resolveGitVersion(): string
    {
        $process = new Process(['git', 'describe', '--tags'], $this->projectDir);

        try {
            $process->mustRun();
        } catch (ProcessFailedException) {
            return self::VERSION_UNAVAILABLE;
        }

        return trim($process->getOutput()) ?: self::VERSION_UNAVAILABLE;
    }
}

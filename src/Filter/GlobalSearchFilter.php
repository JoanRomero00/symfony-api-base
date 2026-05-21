<?php

/**
 * Filtro de búsqueda full-text multi-campo vía parámetro 'q'. Usa UNACCENT para ignorar acentos.
 */

namespace App\Filter;

use ApiPlatform\Doctrine\Common\PropertyHelperTrait;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filtro de búsqueda global reutilizable para cualquier entidad.
 * Se activa con el parámetro 'q' en la URL.
 * Ejemplo: GET /api/inscripcions?q=seminario.
 *
 * Las propiedades en las que buscar se definen en el QueryParameter
 * de cada entidad, así el filtro permanece genérico.
 */
final class GlobalSearchFilter extends AbstractFilter
{
    use PropertyHelperTrait;

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        // Solo se ejecuta si el parámetro es 'q' y tiene valor
        if ('q' !== $property || empty($value) || null === $operation) {
            return;
        }

        // Obtener el QueryParameter 'q' definido en la entidad
        $parameter = $operation->getParameters()?->get('q');
        if (!$parameter) {
            return;
        }

        // Traer la lista de propiedades definidas en el QueryParameter de la entidad
        // Ejemplo: ['id', 'persona.apellido', 'curso.descripcion', ...]
        $searchFields = $parameter->getProperties();
        if (empty($searchFields)) {
            return;
        }

        // Tokenizar el valor de búsqueda por espacios
        // "juan perez" → ["juan", "perez"]
        $tokens = array_filter(explode(' ', trim((string) $value)));
        if (empty($tokens)) {
            return;
        }

        // Alias raíz del QueryBuilder (ej: 'i' para Inscripcion)
        $rootAlias = $queryBuilder->getRootAliases()[0];

        // Por cada token, generar un grupo OR (coincide en al menos un campo)
        // y combinar todos los grupos con AND (todos los tokens deben matchear)
        foreach ($tokens as $token) {
            $orConditions = [];

            // Iterar cada campo en el que se va a buscar
            foreach ($searchFields as $field) {
                $alias = $rootAlias;
                $targetField = $field;

                // Si la propiedad es anidada (ej: persona.apellido),
                // agregar los joins necesarios y obtener el alias correcto
                if ($this->isPropertyNested($field, $resourceClass)) {
                    [$alias, $targetField] = $this->addJoinsForNestedProperty(
                        $field,
                        $rootAlias,
                        $queryBuilder,
                        $queryNameGenerator,
                        $resourceClass,
                        'LEFT', // se coloca un left join para el caso de búsqueda en propiedades anidadas que admiten null
                    );
                }

                // Generar un nombre de parámetro único para cada condición
                // Esto evita colisiones cuando hay múltiples campos (ej: search_1, search_2, ...)
                $paramName = $queryNameGenerator->generateParameterName('search');

                // Construir la condición LIKE case-insensitive e insensible a acentos
                // CONCAT(..., '') castea a string para campos que puedan ser enteros (ej: id)
                // UNACCENT(LOWER(...)) elimina tildes/diacríticos y normaliza a minúsculas
                $orConditions[] = $queryBuilder->expr()->like(
                    "UNACCENT(LOWER(CONCAT($alias.$targetField, '')))",
                    ":$paramName"
                );

                // Asignar el valor de búsqueda al parámetro, en minúsculo, sin acentos y con comodines
                $queryBuilder->setParameter($paramName, '%'.self::removeAccents(strtolower($token)).'%');
            }

            // Si hay condiciones, agregarlas al query conectadas con OR
            // Así basta que coincida en cualquiera de los campos
            if (count($orConditions) > 0) {
                $queryBuilder->andWhere($queryBuilder->expr()->orX(...$orConditions));
            }
        }
    }

    /**
     * Elimina acentos/diacríticos de un string usando transliteración.
     * Se aplica al valor de búsqueda en PHP para que coincida con
     * el UNACCENT() aplicado en la query SQL.
     */
    private static function removeAccents(string $string): string
    {
        return transliterator_transliterate('NFD; [:Nonspacing Mark:] Remove; NFC', $string);
    }

    /**
     * Describe el parámetro 'q' para la documentación OpenAPI/Swagger.
     */
    public function getDescription(string $resourceClass): array
    {
        return [
            'q' => [
                'property' => 'q',
                'type' => 'string',
                'required' => false,
                'openapi' => ['description' => 'Búsqueda global en múltiples campos'],
            ],
        ];
    }
}

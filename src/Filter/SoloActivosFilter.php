<?php

/**
 * Filtro que excluye entidades con baja lógica (fechaBaja IS NOT NULL) vía parámetro 'soloActivos'.
 */

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Filtro reutilizable para entidades con baja lógica (fechaBaja).
 * Activado con el parámetro 'soloActivos' en la URL.
 * Ejemplo: GET /api/tipo_cursos?soloActivos=1.
 */
final class SoloActivosFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ($property !== 'soloActivos' || empty($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere("{$alias}.fechaBaja IS NULL");
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}

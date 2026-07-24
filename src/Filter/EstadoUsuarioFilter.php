<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

final class EstadoUsuarioFilter extends AbstractFilter
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
        if ('estado' !== $property || !is_string($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        if ('activos' === $value) {
            $queryBuilder->andWhere("{$alias}.fechaBaja IS NULL");
        } elseif ('baja' === $value) {
            $queryBuilder->andWhere("{$alias}.fechaBaja IS NOT NULL");
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}

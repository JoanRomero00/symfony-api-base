<?php

/**
 * Filtro de ordenamiento por múltiples campos y relaciones anidadas vía parámetro 'order[campo]=ASC|DESC'.
 */

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

final class CustomOrderFilter extends AbstractFilter
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
        if ($property !== 'order' || !is_array($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameter = $operation?->getParameters()?->get('order');
        $allowedProperties = $parameter?->getProperties() ?? [];

        foreach ($value as $orderProperty => $direction) {
            if ([] !== $allowedProperties && !in_array($orderProperty, $allowedProperties, true)) {
                continue;
            }

            $direction = strtoupper((string) $direction);
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                continue;
            }

            if (str_contains($orderProperty, '.')) {
                $parts = explode('.', $orderProperty);
                $currentAlias = $alias;

                for ($i = 0; $i < count($parts) - 1; ++$i) {
                    $relation = $parts[$i];
                    $joinAlias = $relation;

                    if (!$this->isJoinAdded($queryBuilder, $joinAlias)) {
                        $queryBuilder->leftJoin("{$currentAlias}.{$relation}", $joinAlias);
                    }

                    $currentAlias = $joinAlias;
                }

                $field = end($parts);
                $queryBuilder->addOrderBy("{$currentAlias}.{$field}", $direction);
            } else {
                $queryBuilder->addOrderBy("{$alias}.{$orderProperty}", $direction);
            }
        }
    }

    private function isJoinAdded(QueryBuilder $queryBuilder, string $alias): bool
    {
        $joins = $queryBuilder->getDQLPart('join');
        foreach ($joins as $joinGroup) {
            foreach ($joinGroup as $join) {
                if ($join->getAlias() === $alias) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}

<?php

namespace App\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

final class UsuarioSortFilter extends AbstractFilter
{
    private const SORT_FIELDS = [
        'id' => 'id',
        'username' => 'username',
        'apellido' => 'apellido',
        'nombre' => 'nombre',
        'email' => 'email',
        'fechaAlta' => 'fechaAlta',
        'fechaBaja' => 'fechaBaja',
        'ultimoAcceso' => 'ultimoAcceso',
        'cantidadAccesos' => 'cantidadAccesos',
    ];

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        if ('sort' !== $property || !is_string($value)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $direction = strtoupper((string) ($context['filters']['direction'] ?? 'ASC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'ASC';

        if ('presidencia' === $value) {
            $queryBuilder
                ->leftJoin("{$alias}.presidencia", 'presidencia_sort')
                ->addOrderBy('presidencia_sort.tribu', $direction);

            return;
        }

        $field = self::SORT_FIELDS[$value] ?? 'username';
        $queryBuilder->addOrderBy("{$alias}.{$field}", $direction);
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}

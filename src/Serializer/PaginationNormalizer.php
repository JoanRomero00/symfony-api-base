<?php

/**
 * Normaliza respuestas paginadas con estructura consistente: { items, meta: { total, page, itemsPerPage }, links }.
 */

namespace App\Serializer;

use ApiPlatform\State\Pagination\PaginatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class PaginationNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'PAGINATION_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED] = true;

        if (!$object instanceof PaginatorInterface) {
            throw new \InvalidArgumentException('Expected an instance of PaginatorInterface.');
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            throw new \RuntimeException('No current request available.');
        }

        $items = [];

        foreach ($object as $item) {
            $normalized = $this->normalizer->normalize($item, $format, $context);

            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }

        $totalItems = $object->getTotalItems();
        $itemsPerPage = $object->getItemsPerPage();
        $currentPage = $object->getCurrentPage();
        $totalPages = $itemsPerPage > 0
            ? (int) ceil($totalItems / $itemsPerPage)
            : 1;

        return [
            'items' => $items,
            'meta' => [
                'totalItems' => $totalItems,
                'itemCount' => count($items),
                'itemsPerPage' => $itemsPerPage,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
            ],
            'links' => $this->generateLinks($request, $currentPage, $totalPages),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED])
            && $data instanceof PaginatorInterface
            && $format === 'json';
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            PaginatorInterface::class => true,
        ];
    }

    private function generateLinks($request, int $currentPage, int $totalPages): array
    {
        $buildQuery = function (int $page) use ($request) {
            $query = $request->query->all();
            $query['page'] = $page;

            return http_build_query($query);
        };

        $links = [
            'self' => http_build_query($request->query->all()),
            'first' => $buildQuery(1),
            'last' => $buildQuery(max(1, $totalPages)),
        ];

        if ($currentPage > 1) {
            $links['prev'] = $buildQuery($currentPage - 1);
        }

        if ($currentPage < $totalPages) {
            $links['next'] = $buildQuery($currentPage + 1);
        }

        return $links;
    }
}

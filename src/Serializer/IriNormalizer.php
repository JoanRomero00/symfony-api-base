<?php

/**
 * Inyecta el campo 'iri' en toda respuesta JSON de ApiResource para facilitar la identificación de recursos.
 */

namespace App\Serializer;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\IriConverterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Inyecta el campo 'iri' en todo objeto que sea ApiResource durante la normalización.
 * Cubre: GET individual, items de colección, relaciones embebidas, POST/PATCH responses.
 */
final class IriNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED_PREFIX = 'IRI_NORMALIZER_';

    public function __construct(
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED_PREFIX.spl_object_id($object)] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        try {
            $data['iri'] = $this->iriConverter->getIriFromResource($object);
        } catch (\Throwable) {
        }

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (!is_object($data) || $format !== 'json') {
            return false;
        }

        if (isset($context[self::ALREADY_CALLED_PREFIX.spl_object_id($data)])) {
            return false;
        }

        return !empty((new \ReflectionClass($data))->getAttributes(ApiResource::class));
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }
}

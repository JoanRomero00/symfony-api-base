<?php

/**
 * Clase base para tests funcionales: limpia el rate limiter antes de cada test.
 */

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Trait\AuthenticatedTestTrait;

abstract class AbstractApiTestCase extends ApiTestCase
{
    use AuthenticatedTestTrait;

    protected static ?bool $alwaysBootKernel = true;

    /**
     * Limpia el storage del rate limiter de login (issue #77) para que los
     * intentos fallidos de un test no se filtren al siguiente. El cache es
     * filesystem y persiste entre tests si no se purga.
     */
    protected function setUp(): void
    {
        parent::setUp();

        static::bootKernel();
        static::getContainer()->get('cache.rate_limiter')->clear();
        static::ensureKernelShutdown();
    }
}

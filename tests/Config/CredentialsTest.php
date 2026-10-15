<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Config;

use Estratos\FinkokUtilsBundle\Config\Credentials;
use Estratos\FinkokUtilsBundle\Config\Environment;
use Estratos\FinkokUtilsBundle\Config\Service;
use Estratos\FinkokUtilsBundle\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class CredentialsTest extends TestCase
{
    public function testNormalizesTaxpayerId(): void
    {
        $credentials = new Credentials('matriz', 'u', 'p', ' eku9003173c9 ');

        self::assertSame('EKU9003173C9', $credentials->taxpayerId());
        self::assertSame('EKU9003173C9', $credentials->requireTaxpayerId());
    }

    public function testTaxpayerIdIsOptionalForUtilities(): void
    {
        $credentials = new Credentials('matriz', 'u', 'p');

        self::assertNull($credentials->taxpayerId());

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/reportes de créditos/');
        $credentials->requireTaxpayerId();
    }

    public function testRejectsEmptyUsername(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/usuario de Finkok/');

        new Credentials('matriz', '  ', 'p');
    }

    public function testRejectsEmptyPassword(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/contraseña de Finkok/');

        new Credentials('matriz', 'u', '');
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/nombre/');

        new Credentials('  ', 'u', 'p');
    }

    public function testWithEnvironmentReturnsACopy(): void
    {
        $demo = new Credentials('matriz', 'u', 'p', 'EKU9003173C9', Environment::Demo);
        $production = $demo->withEnvironment(Environment::Production);

        self::assertNotSame($demo, $production);
        self::assertSame(Environment::Demo, $demo->environment());
        self::assertSame(Environment::Production, $production->environment());
        self::assertSame('EKU9003173C9', $production->taxpayerId());
        self::assertSame('matriz', $production->name());
        self::assertSame('u', $production->username());
    }

    public function testWithEnvironmentIsIdempotentForSameEnvironment(): void
    {
        $credentials = new Credentials('matriz', 'u', 'p', null, Environment::Demo);

        self::assertSame($credentials, $credentials->withEnvironment(Environment::Demo));
    }

    public function testDebugInfoHidesThePassword(): void
    {
        $credentials = new Credentials('matriz', 'usuario', 'super-secreto', 'EKU9003173C9');

        $debug = $credentials->__debugInfo();

        self::assertSame('***', $debug['password']);
        self::assertSame('usuario', $debug['username']);
        self::assertStringNotContainsString('super-secreto', print_r($debug, true));
    }

    public function testEndpointOverrideFromProfile(): void
    {
        $credentials = new Credentials(
            'matriz',
            'u',
            'p',
            null,
            Environment::Demo,
            ['utilities' => ['demo' => 'https://proxy.test/utilities']],
        );

        self::assertSame('https://proxy.test/utilities', $credentials->endpoint(Service::Utilities, Environment::Demo));
        self::assertNull($credentials->endpoint(Service::Utilities, Environment::Production));
    }

    public function testBlankEndpointOverrideIsTreatedAsAbsent(): void
    {
        $credentials = new Credentials(
            'matriz',
            'u',
            'p',
            null,
            Environment::Demo,
            ['utilities' => ['demo' => '   ']],
        );

        self::assertNull($credentials->endpoint(Service::Utilities, Environment::Demo));
    }
}

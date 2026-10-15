<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Config;

use Estratos\FinkokUtilsBundle\Config\Credentials;
use Estratos\FinkokUtilsBundle\Config\EndpointResolver;
use Estratos\FinkokUtilsBundle\Config\Environment;
use Estratos\FinkokUtilsBundle\Config\Service;
use PHPUnit\Framework\TestCase;

final class EndpointResolverTest extends TestCase
{
    public function testFallsBackToTheOfficialEndpoint(): void
    {
        $resolver = new EndpointResolver();

        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/utilities',
            $resolver->resolve(Service::Utilities, Environment::Demo),
        );
        self::assertSame(
            'https://facturacion.finkok.com/servicios/soap/utilities',
            $resolver->resolve(Service::Utilities, Environment::Production),
        );
    }

    public function testConfigurationOverridesTheDefault(): void
    {
        $resolver = new EndpointResolver([
            'utilities' => ['demo' => 'https://proxy.test/utilities'],
        ]);

        self::assertSame(
            'https://proxy.test/utilities',
            $resolver->resolve(Service::Utilities, Environment::Demo),
        );
        self::assertSame(
            'https://facturacion.finkok.com/servicios/soap/utilities',
            $resolver->resolve(Service::Utilities, Environment::Production),
            'El ambiente no sobrescrito conserva el endpoint oficial.',
        );
    }

    public function testProfileOverridesTheConfiguration(): void
    {
        $resolver = new EndpointResolver([
            'utilities' => ['demo' => 'https://proxy.test/utilities'],
        ]);

        $credentials = new Credentials(
            'matriz',
            'u',
            'p',
            null,
            Environment::Demo,
            ['utilities' => ['demo' => 'https://perfil.test/utilities']],
        );

        self::assertSame(
            'https://perfil.test/utilities',
            $resolver->resolve(Service::Utilities, Environment::Demo, $credentials),
        );
    }

    public function testBlankConfigurationIsIgnored(): void
    {
        $resolver = new EndpointResolver(['utilities' => ['demo' => '   ']]);

        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/utilities',
            $resolver->resolve(Service::Utilities, Environment::Demo),
        );
    }
}

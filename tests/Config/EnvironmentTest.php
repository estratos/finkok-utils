<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Config;

use Estratos\FinkokUtilsBundle\Config\Environment;
use Estratos\FinkokUtilsBundle\Config\Service;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    public function testServiceNamespaceAndPathsMatchTheWsdl(): void
    {
        self::assertSame('http://facturacion.finkok.com/utilities', Service::Utilities->namespace());
        self::assertSame('/servicios/soap/utilities.wsdl', Service::Utilities->wsdlPath());
        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/utilities.wsdl',
            Service::Utilities->wsdlUrl(Environment::Demo),
        );
        self::assertSame(
            'https://facturacion.finkok.com/servicios/soap/utilities.wsdl',
            Service::Utilities->wsdlUrl(Environment::Production),
        );
    }

    /**
     * El endpoint de invocación vive en `/servicios/soap/utilities`, sin la
     * extensión `.wsdl` que sí tiene la URL del contrato.
     */
    public function testInvocationEndpointHasNoWsdlExtension(): void
    {
        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/utilities',
            Service::Utilities->defaultEndpoint(Environment::Demo),
        );
        self::assertStringNotContainsString('.wsdl', Service::Utilities->defaultEndpoint(Environment::Demo));
    }

    public function testFromSpanishAcceptsCommonAliases(): void
    {
        self::assertSame(Environment::Demo, Environment::fromSpanish('demostración'));
        self::assertSame(Environment::Demo, Environment::fromSpanish('PRUEBAS'));
        self::assertSame(Environment::Demo, Environment::fromSpanish('sandbox'));
        self::assertSame(Environment::Production, Environment::fromSpanish('producción'));
        self::assertSame(Environment::Production, Environment::fromSpanish('live'));
    }

    public function testFromSpanishRejectsUnknownValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Environment::fromSpanish('staging');
    }

    public function testLabelsAndPredicates(): void
    {
        self::assertSame('DEMO', Environment::Demo->label());
        self::assertSame('PRODUCCIÓN', Environment::Production->label());
        self::assertTrue(Environment::Demo->isDemo());
        self::assertFalse(Environment::Demo->isProduction());
        self::assertTrue(Environment::Production->isProduction());
    }
}

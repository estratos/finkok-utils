<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Model;

use Estratos\FinkokUtilsBundle\Exception\ValidationException;
use Estratos\FinkokUtilsBundle\Model\InvoiceType;
use PHPUnit\Framework\TestCase;

final class InvoiceTypeTest extends TestCase
{
    public function testOfficialKeysMatchTheSatCatalog(): void
    {
        self::assertSame('I', InvoiceType::Ingreso->value);
        self::assertSame('E', InvoiceType::Egreso->value);
        self::assertSame('T', InvoiceType::Traslado->value);
        self::assertSame('N', InvoiceType::Nomina->value);
        self::assertSame('P', InvoiceType::Pago->value);
    }

    /**
     * El WSDL envía `invoice_type` como texto libre, así que el bundle acepta
     * tanto la clave oficial como nombres legibles.
     */
    public function testFromKeyAcceptsOfficialKeysAndLegibleNames(): void
    {
        self::assertSame(InvoiceType::Ingreso, InvoiceType::fromKey('I'));
        self::assertSame(InvoiceType::Ingreso, InvoiceType::fromKey('i'));
        self::assertSame(InvoiceType::Ingreso, InvoiceType::fromKey('ingreso'));
        self::assertSame(InvoiceType::Ingreso, InvoiceType::fromKey('  Factura  '));
        self::assertSame(InvoiceType::Egreso, InvoiceType::fromKey('E'));
        self::assertSame(InvoiceType::Egreso, InvoiceType::fromKey('nota de credito'));
        self::assertSame(InvoiceType::Traslado, InvoiceType::fromKey('T'));
        self::assertSame(InvoiceType::Nomina, InvoiceType::fromKey('N'));
        self::assertSame(InvoiceType::Nomina, InvoiceType::fromKey('nómina'));
        self::assertSame(InvoiceType::Pago, InvoiceType::fromKey('P'));
    }

    public function testFromKeyRejectsUnknownValues(): void
    {
        try {
            InvoiceType::fromKey('X');
            self::fail('Se esperaba una ValidationException.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('"X"', $exception->getMessage());
        }
    }

    public function testLabelIsHumanReadable(): void
    {
        self::assertSame('Ingreso', InvoiceType::Ingreso->label());
        self::assertSame('Nómina', InvoiceType::Nomina->label());
    }
}

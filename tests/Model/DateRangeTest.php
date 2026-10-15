<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Model;

use Estratos\FinkokUtilsBundle\Exception\ValidationException;
use Estratos\FinkokUtilsBundle\Model\DateRange;
use Estratos\FinkokUtilsBundle\Model\InvoiceType;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    public function testForMonthCoversTheWholeMonth(): void
    {
        $range = DateRange::forMonth(2026, 2);

        self::assertSame('2026-02-01T00:00:00', $range->fromString());
        // Febrero de 2026 tiene 28 días y el rango debe incluir su último instante.
        self::assertSame('2026-02-28T23:59:59', $range->toString());
        self::assertSame(28, $range->days());
    }

    public function testForMonthHandlesLeapYears(): void
    {
        $range = DateRange::forMonth(2024, 2);

        self::assertSame('2024-02-29T23:59:59', $range->toString());
    }

    public function testForPeriodsSpansMultipleMonths(): void
    {
        $range = DateRange::forPeriods(2026, 1, 2026, 3);

        self::assertSame('2026-01-01T00:00:00', $range->fromString());
        self::assertSame('2026-03-31T23:59:59', $range->toString());
    }

    public function testForDayCoversFullDay(): void
    {
        $range = DateRange::forDay('2026-10-14');

        self::assertSame('2026-10-14T00:00:00', $range->fromString());
        self::assertSame('2026-10-14T23:59:59', $range->toString());
    }

    public function testForYearCoversFullYear(): void
    {
        $range = DateRange::forYear(2026);

        self::assertSame('2026-01-01T00:00:00', $range->fromString());
        self::assertSame('2026-12-31T23:59:59', $range->toString());
    }

    public function testFromStringsExtendsMidnightEndToEndOfDay(): void
    {
        // Un `date_to` sin hora se interpreta como el día completo: enviar
        // T00:00:00 dejaría fuera todos los comprobantes de esa fecha.
        $range = DateRange::fromStrings('2026-01-01', '2026-01-31');

        self::assertSame('2026-01-01T00:00:00', $range->fromString());
        self::assertSame('2026-01-31T23:59:59', $range->toString());
    }

    public function testFromStringsRespectsAnExplicitEndTime(): void
    {
        $range = DateRange::fromStrings('2026-01-01T08:00:00', '2026-01-31T12:30:00');

        self::assertSame('2026-01-01T08:00:00', $range->fromString());
        self::assertSame('2026-01-31T12:30:00', $range->toString());
    }

    public function testRejectsInvertedRange(): void
    {
        $this->expectException(ValidationException::class);

        DateRange::fromStrings('2026-03-01', '2026-01-01');
    }

    public function testRejectsInvalidMonth(): void
    {
        try {
            DateRange::forMonth(2026, 13);
            self::fail('Se esperaba una ValidationException.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('2026-13', $exception->getMessage());
        }
    }

    public function testRejectsYearBefore2000(): void
    {
        $this->expectException(ValidationException::class);

        DateRange::forMonth(1999, 6);
    }

    public function testRejectsEmptyString(): void
    {
        $this->expectException(ValidationException::class);

        DateRange::fromStrings('', '2026-01-01');
    }

    public function testBetweenAcceptsDateTimeInterfaces(): void
    {
        $from = new \DateTimeImmutable('2026-05-01 10:00:00');
        $to = new \DateTimeImmutable('2026-05-31 10:00:00');

        $range = DateRange::between($from, $to);

        self::assertSame('2026-05-01T10:00:00', $range->fromString());
        self::assertSame('2026-05-31T10:00:00', $range->toString());
    }
}

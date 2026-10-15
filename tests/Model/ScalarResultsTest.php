<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Model;

use Estratos\FinkokUtilsBundle\Model\DateTimeResult;
use Estratos\FinkokUtilsBundle\Model\IdCcpResult;
use Estratos\FinkokUtilsBundle\Model\MonedaResult;
use PHPUnit\Framework\TestCase;

final class ScalarResultsTest extends TestCase
{
    public function testDateTimeResultExposesBothStringAndObject(): void
    {
        $result = new DateTimeResult('2026-10-14T18:25:31');

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->hasDatetime());

        $date = $result->toDateTime(new \DateTimeZone('America/Mexico_City'));
        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2026-10-14 18:25:31', $date->format('Y-m-d H:i:s'));
    }

    public function testDateTimeResultWithoutValueIsNotSuccessful(): void
    {
        $result = new DateTimeResult(null, 'El usuario o contraseña son inválidos');

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isError());
        self::assertFalse($result->hasDatetime());
        self::assertNull($result->toDateTime());
    }

    public function testDateTimeResultSurvivesUnparsableValue(): void
    {
        $result = new DateTimeResult('no-es-una-fecha');

        self::assertTrue($result->hasDatetime());
        self::assertNull($result->toDateTime());
    }

    public function testIdCcpResult(): void
    {
        $result = new IdCcpResult('CCEafccdd-e9d4-6bd2-a2fc-1b2e4727d1e5');

        self::assertTrue($result->isSuccess());
        self::assertSame('CCEafccdd-e9d4-6bd2-a2fc-1b2e4727d1e5', $result->idccp());

        self::assertFalse((new IdCcpResult())->isSuccess());
        self::assertFalse((new IdCcpResult(null, 'error'))->isSuccess());
    }

    public function testMonedaResultConvertsToFloat(): void
    {
        $result = new MonedaResult('17.1234');

        self::assertTrue($result->isSuccess());
        self::assertSame('17.1234', $result->moneda());
        self::assertSame(17.1234, $result->rate());
    }

    public function testMonedaResultHandlesNonNumericValue(): void
    {
        $result = new MonedaResult('N/A');

        self::assertTrue($result->hasMoneda());
        self::assertNull($result->rate());
        self::assertTrue($result->isSuccess());
    }
}

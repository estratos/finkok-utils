<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Model;

use Estratos\FinkokUtilsBundle\Model\ModelDate;
use PHPUnit\Framework\TestCase;

final class ModelDateTest extends TestCase
{
    public function testParsesDateWithSixDecimalPlaces(): void
    {
        $date = ModelDate::parse('2019-01-13T14:15:16.171819', new \DateTimeZone('UTC'));

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2019-01-13 14:15:16.171819', $date->format('Y-m-d H:i:s.u'));
    }

    /**
     * PHP rechaza más de seis decimales en el constructor; Finkok puede enviarlos,
     * así que se truncan en lugar de fallar.
     */
    public function testTruncatesExcessiveDecimalPlaces(): void
    {
        $date = ModelDate::parse('2019-01-13T14:15:16.1718191234', new \DateTimeZone('UTC'));

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2019-01-13 14:15:16.171819', $date->format('Y-m-d H:i:s.u'));
    }

    public function testPreservesOffsetAfterFraction(): void
    {
        $date = ModelDate::parse('2019-01-13T14:15:16.1718191-06:00');

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('-06:00', $date->format('P'));
    }

    public function testParsesPlainDate(): void
    {
        $date = ModelDate::parse('2019-01-13');

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2019-01-13', $date->format('Y-m-d'));
    }

    public function testReturnsNullForEmptyOrGarbage(): void
    {
        self::assertNull(ModelDate::parse(null));
        self::assertNull(ModelDate::parse('   '));
        self::assertNull(ModelDate::parse('no-es-una-fecha'));
    }
}

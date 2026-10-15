<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Model;

use Estratos\FinkokUtilsBundle\Model\ReportCreditItem;
use Estratos\FinkokUtilsBundle\Model\ReportCreditResult;
use Estratos\FinkokUtilsBundle\Model\ReportTotalItem;
use Estratos\FinkokUtilsBundle\Model\ReportTotalResult;
use Estratos\FinkokUtilsBundle\Model\ReportUuidItem;
use Estratos\FinkokUtilsBundle\Model\ReportUuidResult;
use PHPUnit\Framework\TestCase;

final class ReportResultsTest extends TestCase
{
    public function testReportCreditSumsCredits(): void
    {
        $result = new ReportCreditResult([
            new ReportCreditItem('100', '2019-01-13T14:15:16.171819', 'EKU9003173C9'),
            new ReportCreditItem('200', '2019-01-15T16:17:18.192021', 'EKU9003173C9'),
        ]);

        self::assertTrue($result->isSuccess());
        self::assertSame(2, $result->count());
        self::assertSame(300, $result->totalCredits());
        self::assertFalse($result->isEmpty());
    }

    public function testReportCreditIgnoresNonNumericCreditsWhenSumming(): void
    {
        $result = new ReportCreditResult([
            new ReportCreditItem('100'),
            new ReportCreditItem('N/A'),
            new ReportCreditItem(null),
        ]);

        self::assertSame(3, $result->count());
        self::assertSame(100, $result->totalCredits());
    }

    public function testReportCreditEmptyIsStillASuccess(): void
    {
        $result = new ReportCreditResult();

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->isEmpty());
        self::assertSame(0, $result->totalCredits());
        self::assertSame([], $result->toArray());
    }

    public function testReportCreditErrorIsNotASuccess(): void
    {
        $result = new ReportCreditResult([], 'El usuario o contraseña son inválidos');

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isError());
    }

    public function testReportTotalExposesFirstRowTotal(): void
    {
        $result = new ReportTotalResult([
            new ReportTotalItem('123', 'EKU9003173C9'),
        ]);

        self::assertTrue($result->isSuccess());
        self::assertSame(123, $result->total());
        self::assertSame('EKU9003173C9', $result->first()?->taxpayerId);
        self::assertSame(123, $result->first()?->totalAsInt());
    }

    public function testReportTotalWithoutRowsReturnsZero(): void
    {
        $result = new ReportTotalResult();

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->isEmpty());
        self::assertSame(0, $result->total());
        self::assertNull($result->first());
    }

    public function testReportTotalHandlesNonNumericTotal(): void
    {
        $item = new ReportTotalItem('N/A', 'EKU9003173C9');

        self::assertNull($item->totalAsInt());
        self::assertSame(0, (new ReportTotalResult([$item]))->total());
    }

    public function testReportUuidExposesUuidsAndInvoices(): void
    {
        $result = new ReportUuidResult([
            new ReportUuidItem('12345678-1234-1234-1234-000000000001', '2019-01-13T14:15:16', 'EKU9003173C9', 'URE180429TM6', '1160.00'),
            new ReportUuidItem('12345678-1234-1234-1234-000000000002', '2019-01-13T14:15:17'),
        ]);

        self::assertTrue($result->isSuccess());
        self::assertSame(2, $result->count());
        self::assertCount(2, $result->items());
        self::assertSame(
            [
                '12345678-1234-1234-1234-000000000001',
                '12345678-1234-1234-1234-000000000002',
            ],
            $result->uuids(),
        );

        $first = $result->invoices()[0];
        self::assertSame(1160.0, $first->totalAsFloat());
        self::assertInstanceOf(\DateTimeImmutable::class, $first->dateTime('UTC'));
    }

    public function testReportUuidSkipsRowsWithoutUuidWhenListingUuids(): void
    {
        $result = new ReportUuidResult([
            new ReportUuidItem(null, '2019-01-13T14:15:16'),
            new ReportUuidItem('12345678-1234-1234-1234-000000000002'),
            new ReportUuidItem('   '),
        ]);

        self::assertCount(3, $result->invoices());
        self::assertSame(['12345678-1234-1234-1234-000000000002'], $result->uuids());
    }

    public function testReportUuidEmpty(): void
    {
        $result = new ReportUuidResult();

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->isEmpty());
        self::assertSame([], $result->uuids());
    }

    public function testItemsSerialiseToPlainArrays(): void
    {
        $result = new ReportUuidResult([
            new ReportUuidItem('12345678-1234-1234-1234-000000000001', '2019-01-13T14:15:16', 'EKU9003173C9'),
        ]);

        self::assertSame(
            [[
                'uuid' => '12345678-1234-1234-1234-000000000001',
                'date' => '2019-01-13T14:15:16',
                'taxpayer_id' => 'EKU9003173C9',
                'rtaxpayer_id' => null,
                'total' => null,
            ]],
            $result->toArray(),
        );
    }
}

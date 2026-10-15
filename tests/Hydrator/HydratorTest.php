<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Hydrator;

use Estratos\FinkokUtilsBundle\Hydrator\ReportCreditHydrator;
use Estratos\FinkokUtilsBundle\Hydrator\ReportTotalHydrator;
use Estratos\FinkokUtilsBundle\Hydrator\ReportUuidHydrator;
use Estratos\FinkokUtilsBundle\Hydrator\ScalarResultHydrator;
use Estratos\FinkokUtilsBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Los hidratadores se prueban contra fixtures que reproducen las respuestas
 * documentadas por Finkok y las variantes reales (contenedores alternativos,
 * filas mínimas, resultados vacíos).
 */
final class HydratorTest extends TestCase
{
    public function testScalarHydratorsReadTheirValueElement(): void
    {
        $datetime = ScalarResultHydrator::datetime(Fixtures::resultElement('datetime-success.xml', 'datetime'));
        self::assertSame('2026-10-14T18:25:31', $datetime->datetime());
        self::assertTrue($datetime->isSuccess());

        $idccp = ScalarResultHydrator::idCcp(Fixtures::resultElement('get-idccp-success.xml', 'get_idccp'));
        self::assertSame('CCC47D35-5855-4E23-8A52-3AB33221AE09', $idccp->idccp());

        $moneda = ScalarResultHydrator::moneda(Fixtures::resultElement('get-moneda-usd-success.xml', 'get_moneda_usd'));
        self::assertSame(17.1234, $moneda->rate());
    }

    public function testScalarHydratorsSurviveAnEmptyBody(): void
    {
        self::assertFalse(ScalarResultHydrator::datetime(null)->hasDatetime());
        self::assertFalse(ScalarResultHydrator::downloadXml(null)->hasXml());
        self::assertFalse(ScalarResultHydrator::idCcp(null)->hasIdccp());
        self::assertFalse(ScalarResultHydrator::moneda(null)->hasMoneda());
    }

    public function testDownloadXmlHydratorUnescapesTheCfdi(): void
    {
        $result = ScalarResultHydrator::downloadXml(Fixtures::resultElement('get-xml-success.xml', 'get_xml'));

        self::assertNotNull($result->xml());
        self::assertStringStartsWith('<?xml', (string) $result->xml());
        self::assertTrue($result->isXmlDocument());
    }

    public function testReportCreditHydratorReadsTheWsdlContainer(): void
    {
        $result = ReportCreditHydrator::hydrate(Fixtures::resultElement('report-credit-success.xml', 'report_credit'));

        self::assertSame(2, $result->count());
        self::assertSame(300, $result->totalCredits());
        self::assertSame('100', $result->items()[0]->credit);
    }

    public function testReportTotalHydratorReadsTheWsdlContainer(): void
    {
        $result = ReportTotalHydrator::hydrate(Fixtures::resultElement('report-total-success.xml', 'report_total'));

        self::assertSame(1, $result->count());
        self::assertSame(123, $result->total());
        self::assertSame('EKU9003173C9', $result->first()?->taxpayerId);
    }

    public function testReportUuidHydratorReadsTheInvoicesContainer(): void
    {
        $result = ReportUuidHydrator::hydrate(Fixtures::resultElement('report-uuid-success.xml', 'report_uuid'));

        self::assertSame(2, $result->count());
        self::assertSame('12345678-1234-1234-1234-000000000001', $result->invoices()[0]->uuid);
        self::assertSame('URE180429TM6', $result->invoices()[0]->receiverTaxpayerId);
        self::assertSame('1160.00', $result->invoices()[0]->total);
    }

    public function testReportUuidHydratorToleratesMinimalRows(): void
    {
        $result = ReportUuidHydrator::hydrate(Fixtures::resultElement('report-uuid-minimal.xml', 'report_uuid'));

        self::assertSame(1, $result->count());
        self::assertNull($result->invoices()[0]->taxpayerId);
        self::assertNull($result->invoices()[0]->total);
    }

    /**
     * El servidor no siempre respeta el nombre del contenedor que declara el WSDL.
     */
    public function testReportUuidHydratorAcceptsResultAsContainer(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:s0="apps.services.soap.core.views">
              <soap:Body>
                <report_uuidResponse xmlns="http://facturacion.finkok.com/utilities">
                  <report_uuidResult>
                    <s0:result>
                      <s0:ReportUUID><s0:uuid>12345678-1234-1234-1234-000000000042</s0:uuid></s0:ReportUUID>
                    </s0:result>
                  </report_uuidResult>
                </report_uuidResponse>
              </soap:Body>
            </soap:Envelope>
            XML;

        $response = new \Estratos\FinkokUtilsBundle\Soap\SoapResponse($xml);
        $result = ReportUuidHydrator::hydrate($response->result('report_uuid'));

        self::assertSame(['12345678-1234-1234-1234-000000000042'], $result->uuids());
    }

    /**
     * `report_uuid` con los ReportUUID colgando directamente del resultado.
     */
    public function testReportUuidHydratorAcceptsFlatRows(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:s0="apps.services.soap.core.views">
              <soap:Body>
                <report_uuidResponse xmlns="http://facturacion.finkok.com/utilities">
                  <report_uuidResult>
                    <s0:ReportUUID><s0:uuid>12345678-1234-1234-1234-000000000007</s0:uuid></s0:ReportUUID>
                  </report_uuidResult>
                </report_uuidResponse>
              </soap:Body>
            </soap:Envelope>
            XML;

        $response = new \Estratos\FinkokUtilsBundle\Soap\SoapResponse($xml);
        $result = ReportUuidHydrator::hydrate($response->result('report_uuid'));

        self::assertSame(['12345678-1234-1234-1234-000000000007'], $result->uuids());
    }

    public function testReportHydratorsSurviveAnEmptyBody(): void
    {
        self::assertTrue(ReportCreditHydrator::hydrate(null)->isEmpty());
        self::assertTrue(ReportTotalHydrator::hydrate(null)->isEmpty());
        self::assertTrue(ReportUuidHydrator::hydrate(null)->isEmpty());
    }

    public function testEmptyContainersProduceNoItemsButStaySuccessful(): void
    {
        $credit = ReportCreditHydrator::hydrate(Fixtures::resultElement('report-credit-empty.xml', 'report_credit'));
        self::assertTrue($credit->isEmpty());
        self::assertTrue($credit->isSuccess());

        $total = ReportTotalHydrator::hydrate(Fixtures::resultElement('report-total-empty.xml', 'report_total'));
        self::assertTrue($total->isEmpty());
        self::assertTrue($total->isSuccess());

        $uuid = ReportUuidHydrator::hydrate(Fixtures::resultElement('report-uuid-empty.xml', 'report_uuid'));
        self::assertTrue($uuid->isEmpty());
        self::assertTrue($uuid->isSuccess());
    }

    public function testHydratorsPreserveTheRawResponseForAuditing(): void
    {
        $raw = Fixtures::content('report-uuid-success.xml');
        $result = ReportUuidHydrator::hydrate(
            Fixtures::resultElement('report-uuid-success.xml', 'report_uuid'),
            $raw,
        );

        self::assertSame($raw, $result->rawResponse());
    }
}

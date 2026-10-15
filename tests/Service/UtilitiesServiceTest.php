<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Service;

use Estratos\FinkokUtilsBundle\Config\Environment;
use Estratos\FinkokUtilsBundle\Exception\ValidationException;
use Estratos\FinkokUtilsBundle\Model\DateRange;
use Estratos\FinkokUtilsBundle\Model\InvoiceType;
use Estratos\FinkokUtilsBundle\Soap\SoapNamespaces;
use Estratos\FinkokUtilsBundle\Tests\Concerns\BuildsUtilitiesService;
use Estratos\FinkokUtilsBundle\Tests\Fakes\RecordingSoapTransport;
use Estratos\FinkokUtilsBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

/**
 * Verifica el contrato con `utilities.wsdl`: operación, namespace y argumentos.
 */
final class UtilitiesServiceTest extends TestCase
{
    use BuildsUtilitiesService;

    public function testDatetimeSendsUsernamePasswordAndZipcodeInWsdlOrder(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('datetime-success.xml')]);
        $service = $this->service($transport);

        $result = $service->datetime('86000');

        self::assertTrue($result->isSuccess());
        self::assertSame('2026-10-14T18:25:31', $result->datetime());

        $request = $transport->lastRequest();
        self::assertSame('datetime', $request->operation);
        self::assertSame(SoapNamespaces::UTILITIES, $request->namespace);
        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/utilities',
            $request->endpoint,
        );

        // Orden declarado en el WSDL: username, password, zipcode.
        self::assertSame(
            ['username', 'password', 'zipcode'],
            array_keys($transport->lastArguments()),
        );
        self::assertSame('86000', $transport->lastArguments()['zipcode']);
    }

    public function testDatetimeOmitsZipcodeWhenNotProvided(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('datetime-success.xml')]);
        $service = $this->service($transport);

        $service->datetime();

        $arguments = $transport->lastArguments();
        self::assertArrayNotHasKey('zipcode', $arguments);
        self::assertSame(['username', 'password'], array_keys($arguments));
    }

    public function testDatetimeReportsBusinessError(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('datetime-credentials-error.xml')]);
        $service = $this->service($transport);

        $result = $service->datetime();

        self::assertFalse($result->isSuccess());
        self::assertTrue($result->isError());
        self::assertSame('El usuario o contraseña son inválidos', $result->error());
        self::assertNull($result->datetime());
    }

    public function testDatetimeConvertsToDateTimeImmutable(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('datetime-success.xml')]);
        $result = $this->service($transport)->datetime();

        $dateTime = $result->toDateTime(new \DateTimeZone('America/Mexico_City'));

        self::assertInstanceOf(\DateTimeImmutable::class, $dateTime);
        self::assertSame('2026-10-14 18:25:31', $dateTime->format('Y-m-d H:i:s'));
    }

    public function testDatetimeRejectsInvalidZipcodeLocally(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('datetime-success.xml')]);
        $service = $this->service($transport);

        try {
            $service->datetime('123');
            self::fail('Se esperaba una ValidationException.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('[0-9]{5}', $exception->getMessage());
        }

        // La petición inválida no debe llegar a Finkok.
        self::assertSame(0, $transport->callCount());
    }

    public function testDownloadXmlSendsWsdlOrderedArguments(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('get-xml-success.xml')]);
        $service = $this->service($transport);

        $result = $service->downloadXml('12345678-1234-1234-1234-000000000001');

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->isXmlDocument());
        self::assertFalse($result->looksLikePath());

        // Orden declarado en el WSDL: username, password, uuid, taxpayer_id, invoice_type.
        self::assertSame(
            ['username', 'password', 'uuid', 'taxpayer_id', 'invoice_type'],
            array_keys($transport->lastArguments()),
        );

        $arguments = $transport->lastArguments();
        self::assertSame('12345678-1234-1234-1234-000000000001', $arguments['uuid']);
        self::assertSame('EKU9003173C9', $arguments['taxpayer_id']);
        // Por omisión se consulta un CFDI de ingreso.
        self::assertSame('I', $arguments['invoice_type']);
    }

    public function testDownloadXmlDecodesTheEmbeddedCfdi(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('get-xml-success.xml')]);
        $result = $this->service($transport)->downloadXml('12345678-1234-1234-1234-000000000001');

        $xml = (string) $result->xml();

        self::assertStringStartsWith('<?xml', $xml);
        self::assertStringContainsString('cfdi:Comprobante', $xml);
        self::assertStringNotContainsString('&lt;', $xml);
        self::assertInstanceOf(\DOMDocument::class, $result->toDocument());
    }

    public function testDownloadXmlFlagsFilePathResponseInsteadOfReturningItAsCfdi(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('get-xml-path-response.xml')]);
        $service = $this->service($transport, xmlMaxAttempts: 2);

        $result = $service->downloadXml('12345678-1234-1234-1234-000000000009');

        self::assertTrue($result->looksLikePath());
        self::assertFalse($result->isXmlDocument());
        self::assertFalse($result->isSuccess());
        // Un intento por cada intento configurado.
        self::assertSame(2, $transport->callCount());
    }

    public function testDownloadXmlRetriesUntilContentArrives(): void
    {
        $transport = new RecordingSoapTransport([
            Fixtures::content('get-xml-path-response.xml'),
            Fixtures::content('get-xml-success.xml'),
        ]);
        $service = $this->service($transport, xmlMaxAttempts: 3);

        $result = $service->downloadXml('12345678-1234-1234-1234-000000000001');

        self::assertTrue($result->isXmlDocument());
        self::assertFalse($result->looksLikePath());
        self::assertSame(2, $transport->callCount());
    }

    public function testDownloadXmlReportsNotFound(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('get-xml-not-found.xml')]);
        $result = $this->service($transport)->downloadXml('12345678-1234-1234-1234-000000000001');

        self::assertTrue($result->isError());
        self::assertSame('No se encontró el XML solicitado', $result->error());
        self::assertFalse($result->isSuccess());
        self::assertNull($result->xml());
    }

    public function testDownloadXmlAcceptsExplicitInvoiceType(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('get-xml-success.xml')]);
        $service = $this->service($transport);

        $service->downloadXml(
            '12345678-1234-1234-1234-000000000001',
            'URE180429TM6',
            InvoiceType::Nomina,
        );

        $arguments = $transport->lastArguments();
        self::assertSame('URE180429TM6', $arguments['taxpayer_id']);
        self::assertSame('N', $arguments['invoice_type']);
    }

    public function testDownloadXmlRejectsMalformedUuid(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('get-xml-success.xml')]);
        $service = $this->service($transport);

        try {
            $service->downloadXml('no-es-un-uuid');
            self::fail('Se esperaba una ValidationException.');
        } catch (ValidationException $exception) {
            // El UUID se normaliza a mayúsculas antes de validarse.
            self::assertStringContainsStringIgnoringCase('no-es-un-uuid', $exception->getMessage());
        }

        // El UUID inválido no debe llegar a Finkok.
        self::assertSame(0, $transport->callCount());
    }

    public function testReportCreditSendsTaxpayerIdFromProfile(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-credit-success.xml')]);
        $service = $this->service($transport);

        $result = $service->reportCredit();

        self::assertTrue($result->isSuccess());
        self::assertCount(2, $result->items());
        self::assertSame(300, $result->totalCredits());

        // Orden declarado en el WSDL: username, password, taxpayer_id.
        self::assertSame(
            ['username', 'password', 'taxpayer_id'],
            array_keys($transport->lastArguments()),
        );
        self::assertSame('EKU9003173C9', $transport->lastArguments()['taxpayer_id']);
    }

    public function testReportCreditParsesSubSecondDates(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-credit-success.xml')]);
        $result = $this->service($transport)->reportCredit();

        $first = $result->items()[0];

        self::assertSame('100', $first->credit);
        self::assertSame(100, $first->creditAsInt());
        self::assertSame('EKU9003173C9', $first->taxpayerId);

        // La fecha trae seis decimales de fracción de segundo en el fixture.
        $date = $first->dateTime(new \DateTimeZone('UTC'));
        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2019-01-13 14:15:16', $date->format('Y-m-d H:i:s'));
    }

    public function testReportCreditHandlesEmptyResultAsSuccess(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-credit-empty.xml')]);
        $result = $this->service($transport)->reportCredit();

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->isEmpty());
        self::assertSame(0, $result->count());
        self::assertSame(0, $result->totalCredits());
        self::assertNull($result->error());
    }

    public function testReportCreditRequiresAnRfc(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-credit-success.xml')]);
        $service = $this->service($transport, $this->credentials(taxpayerId: null));

        $this->expectException(\Estratos\FinkokUtilsBundle\Exception\ConfigurationException::class);

        $service->reportCredit();
    }

    public function testReportTotalSendsDateRangeInWsdlOrder(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-total-success.xml')]);
        $service = $this->service($transport);

        $result = $service->reportTotal(DateRange::forMonth(2019, 1));

        self::assertTrue($result->isSuccess());
        self::assertSame(123, $result->total());
        self::assertSame('EKU9003173C9', $result->first()?->taxpayerId);

        // Orden declarado en el WSDL: username, password, taxpayer_id, date_from, date_to.
        self::assertSame(
            ['username', 'password', 'taxpayer_id', 'date_from', 'date_to'],
            array_keys($transport->lastArguments()),
        );

        $arguments = $transport->lastArguments();
        self::assertSame('2019-01-01T00:00:00', $arguments['date_from']);
        // El mes completo debe incluir el último día, no detenerse en su medianoche.
        self::assertSame('2019-01-31T23:59:59', $arguments['date_to']);
    }

    public function testReportTotalHandlesEmptyResult(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-total-empty.xml')]);
        $result = $this->service($transport)->reportTotal(DateRange::forMonth(2019, 8));

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->isEmpty());
        self::assertSame(0, $result->total());
        self::assertNull($result->first());
    }

    public function testReportUuidParsesInvoices(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-uuid-success.xml')]);
        $service = $this->service($transport);

        $result = $service->reportUuid(DateRange::forMonth(2019, 1));

        self::assertTrue($result->isSuccess());
        self::assertCount(2, $result->invoices());
        self::assertSame('report_uuid', $transport->lastRequest()->operation);

        $first = $result->invoices()[0];
        self::assertSame('12345678-1234-1234-1234-000000000001', $first->uuid);
        self::assertSame('EKU9003173C9', $first->taxpayerId);
        self::assertSame('URE180429TM6', $first->receiverTaxpayerId);
        self::assertSame(1160.0, $first->totalAsFloat());

        self::assertSame(
            [
                '12345678-1234-1234-1234-000000000001',
                '12345678-1234-1234-1234-000000000002',
            ],
            $result->uuids(),
        );
    }

    public function testReportUuidToleratesMinimalRows(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-uuid-minimal.xml')]);
        $result = $this->service($transport)->reportUuid(DateRange::forMonth(2019, 1));

        $first = $result->invoices()[0];

        self::assertSame('12345678-1234-1234-1234-000000000001', $first->uuid);
        self::assertNull($first->taxpayerId);
        self::assertNull($first->receiverTaxpayerId);
        self::assertNull($first->total);
        self::assertSame(
            ['12345678-1234-1234-1234-000000000001'],
            $result->uuids(),
        );
    }

    public function testReportUuidHandlesEmptyResult(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-uuid-empty.xml')]);
        $result = $this->service($transport)->reportUuid(DateRange::forMonth(2019, 1));

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->isEmpty());
        self::assertSame([], $result->uuids());
    }

    public function testGetIdCcpReadsIdccpField(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('get-idccp-success.xml')]);
        $service = $this->service($transport);

        $result = $service->getIdCcp();

        self::assertTrue($result->isSuccess());
        self::assertSame('CCEafccdd-e9d4-6bd2-a2fc-1b2e4727d1e5', $result->idccp());
        self::assertSame('get_idccp', $transport->lastRequest()->operation);
        self::assertSame(['username', 'password'], array_keys($transport->lastArguments()));
    }

    public function testGetMonedaUsdReadsRate(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('get-moneda-usd-success.xml')]);
        $service = $this->service($transport);

        $result = $service->getMonedaUsd();

        self::assertTrue($result->isSuccess());
        self::assertSame('17.1234', $result->moneda());
        self::assertSame(17.1234, $result->rate());
        self::assertSame('get_moneda_usd', $transport->lastRequest()->operation);
    }

    public function testUsesProductionEndpointWhenProfileIsProduction(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('datetime-success.xml')]);
        $service = $this->service(
            $transport,
            $this->credentials(environment: Environment::Production),
        );

        $service->datetime();

        self::assertSame(
            'https://facturacion.finkok.com/servicios/soap/utilities',
            $transport->lastRequest()->endpoint,
        );
    }

    public function testExplicitCredentialsOverrideTheDefaultProfile(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-credit-success.xml')]);
        $service = $this->service($transport);

        $otro = $this->credentials(
            username: 'usuario.sucursal',
            password: 'secreto.sucursal',
            taxpayerId: 'URE180429TM6',
        );

        $service->reportCredit(credentials: $otro);

        $arguments = $transport->lastArguments();
        self::assertSame('usuario.sucursal', $arguments['username']);
        self::assertSame('secreto.sucursal', $arguments['password']);
        self::assertSame('URE180429TM6', $arguments['taxpayer_id']);
    }

    public function testExplicitTaxpayerIdOverridesTheProfile(): void
    {
        $transport = new RecordingSoapTransport([Fixtures::content('report-total-success.xml')]);
        $service = $this->service($transport);

        $service->reportTotal(DateRange::forMonth(2019, 1), taxpayerId: 'URE180429TM6');

        self::assertSame('URE180429TM6', $transport->lastArguments()['taxpayer_id']);
    }
}

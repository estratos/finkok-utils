<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Soap;

use Estratos\FinkokUtilsBundle\Soap\SoapNamespaces;
use Estratos\FinkokUtilsBundle\Soap\SoapResponse;
use Estratos\FinkokUtilsBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

final class SoapResponseTest extends TestCase
{
    public function testReadsResultElementOfOperation(): void
    {
        $response = new SoapResponse(Fixtures::content('datetime-success.xml'));

        self::assertTrue($response->isSoapEnvelope());
        self::assertTrue($response->isXml());
        self::assertFalse($response->hasFault());

        $result = $response->result('datetime');

        self::assertInstanceOf(\DOMElement::class, $result);
        self::assertSame('datetimeResult', $result->localName);
        // El WSDL declara `datetimeResult` dentro del propio esquema del servicio
        // (`elementFormDefault="qualified"`), no en el namespace compartido de
        // Finkok: ahí solo viven los tipos de resultado complejos.
        self::assertSame(SoapNamespaces::UTILITIES, $result->namespaceURI);
    }

    public function testReadsResultWithoutOperationHint(): void
    {
        $response = new SoapResponse(Fixtures::content('report-total-success.xml'));

        $result = $response->result();

        self::assertInstanceOf(\DOMElement::class, $result);
        self::assertSame('report_totalResult', $result->localName);
    }

    public function testDetectsSoapFault(): void
    {
        $response = new SoapResponse(Fixtures::content('soap-fault.xml'), 500);

        self::assertTrue($response->hasFault());

        $fault = $response->fault();
        self::assertNotNull($fault);
        self::assertSame('soap:Client', $fault->code);
        self::assertStringContainsString('elemento inesperado', (string) $fault->message);
        self::assertTrue($fault->isClientFault());
    }

    public function testAssertNoFaultThrowsWithEndpointContext(): void
    {
        $response = new SoapResponse(Fixtures::content('soap-fault.xml'), 500);

        try {
            $response->assertNoFault('https://demo-facturacion.finkok.com/servicios/soap/utilities', 'datetime');
            self::fail('Se esperaba una SoapFaultException.');
        } catch (\Estratos\FinkokUtilsBundle\Exception\SoapFaultException $exception) {
            self::assertSame('soap:Client', $exception->getFaultCode());
            self::assertSame('datetime', $exception->getOperation());
            self::assertStringContainsString('datetime', $exception->getMessage());
        }
    }

    /**
     * La página de error de un proxy puede ser XML bien formado (`<html>…</html>`),
     * así que `isXml()` por sí solo no basta: lo que descarta el cuerpo es que no
     * sea un envelope SOAP.
     */
    public function testHtmlErrorPageIsNotASoapEnvelope(): void
    {
        $response = new SoapResponse(Fixtures::content('http-502.html'), 502);

        self::assertTrue($response->isXml(), 'El HTML del fixture es XML bien formado.');
        self::assertFalse($response->isSoapEnvelope());
        self::assertNull($response->envelope());
        self::assertNull($response->result('datetime'));
        self::assertFalse($response->hasFault());
    }

    public function testPlainTextBodyIsNotXmlAtAll(): void
    {
        $response = new SoapResponse('esto no es xml');

        self::assertFalse($response->isXml());
        self::assertFalse($response->isSoapEnvelope());
        self::assertNull($response->result('datetime'));
    }

    public function testEmptyBodyYieldsNoResult(): void
    {
        $response = new SoapResponse(Fixtures::content('empty-body.xml'));

        self::assertTrue($response->isSoapEnvelope());
        self::assertNull($response->result('datetime'));
        self::assertNull($response->body()?->firstElementChild);
    }

    public function testDocumentThrowsOnMalformedXml(): void
    {
        // Etiquetas desbalanceadas: `loadXML` falla y se registran errores de libxml.
        $response = new SoapResponse('<root><a></root>');

        self::assertFalse($response->isXml());

        $this->expectException(\Estratos\FinkokUtilsBundle\Exception\UnexpectedResponseException::class);

        $response->document();
    }
}

<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Soap;

use Estratos\FinkokUtilsBundle\Soap\SoapNamespaces;
use Estratos\FinkokUtilsBundle\Soap\SoapRequest;
use PHPUnit\Framework\TestCase;

final class SoapRequestTest extends TestCase
{
    public function testBuildsDocumentLiteralEnvelopeForUtilities(): void
    {
        $request = new SoapRequest(
            endpoint: 'https://demo-facturacion.finkok.com/servicios/soap/utilities',
            operation: 'report_credit',
            namespace: SoapNamespaces::UTILITIES,
            arguments: [
                'username' => 'usuario',
                'password' => 'secreto',
                'taxpayer_id' => 'EKU9003173C9',
            ],
        );

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($request->toXml()));

        $envelope = $document->documentElement;
        self::assertNotNull($envelope);
        self::assertSame('Envelope', $envelope->localName);
        self::assertSame(SoapNamespaces::ENVELOPE, $envelope->namespaceURI);

        $body = $envelope->getElementsByTagNameNS(SoapNamespaces::ENVELOPE, 'Body')->item(0);
        self::assertInstanceOf(\DOMElement::class, $body);

        $operation = $body->firstElementChild;
        self::assertInstanceOf(\DOMElement::class, $operation);
        self::assertSame('report_credit', $operation->localName);
        // El elemento de la operación vive en el targetNamespace del servicio.
        self::assertSame(SoapNamespaces::UTILITIES, $operation->namespaceURI);

        // Los argumentos también deben ir calificados (elementFormDefault="qualified").
        self::assertSame('usuario', $operation->getElementsByTagNameNS(SoapNamespaces::UTILITIES, 'username')->item(0)?->textContent);
        self::assertSame('EKU9003173C9', $operation->getElementsByTagNameNS(SoapNamespaces::UTILITIES, 'taxpayer_id')->item(0)?->textContent);
    }

    public function testSoapActionMatchesOperationName(): void
    {
        $request = new SoapRequest('https://example.test/utilities', 'datetime', SoapNamespaces::UTILITIES);

        self::assertSame('datetime', $request->soapAction());
    }

    public function testOmitsNullAndEmptyArguments(): void
    {
        $request = new SoapRequest(
            endpoint: 'https://example.test/utilities',
            operation: 'datetime',
            namespace: SoapNamespaces::UTILITIES,
            arguments: [
                'username' => 'usuario',
                'password' => 'secreto',
                'zipcode' => null,
                'otro' => '   ',
            ],
        );

        $xml = $request->toXml();

        self::assertStringNotContainsString('zipcode', $xml);
        self::assertStringNotContainsString('otro', $xml);
        self::assertStringContainsString('username', $xml);
    }

    public function testEscapesXmlSpecialCharactersInArguments(): void
    {
        $request = new SoapRequest(
            endpoint: 'https://example.test/utilities',
            operation: 'report_credit',
            namespace: SoapNamespaces::UTILITIES,
            arguments: ['taxpayer_id' => 'A&B<C>'],
        );

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($request->toXml()));

        $node = $document->getElementsByTagNameNS(SoapNamespaces::UTILITIES, 'taxpayer_id')->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);
        self::assertSame('A&B<C>', $node->textContent);
    }

    public function testDebugArgumentsMasksPassword(): void
    {
        $request = new SoapRequest(
            endpoint: 'https://example.test/utilities',
            operation: 'datetime',
            namespace: SoapNamespaces::UTILITIES,
            arguments: ['username' => 'usuario', 'password' => 'secreto'],
        );

        $debug = $request->debugArguments();

        self::assertSame('usuario', $debug['username']);
        self::assertSame('***', $debug['password']);
    }
}

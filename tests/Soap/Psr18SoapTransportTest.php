<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Soap;

use Estratos\FinkokUtilsBundle\Exception\TransportException;
use Estratos\FinkokUtilsBundle\Exception\UnexpectedResponseException;
use Estratos\FinkokUtilsBundle\Soap\HttpClientSoapTransport;
use Estratos\FinkokUtilsBundle\Soap\Psr18\Psr18RequestHandler;
use Estratos\FinkokUtilsBundle\Soap\SoapNamespaces;
use Estratos\FinkokUtilsBundle\Soap\SoapRequest;
use Estratos\FinkokUtilsBundle\Tests\Fakes\RecordingPsr18Client;
use Estratos\FinkokUtilsBundle\Tests\Fixtures;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

/**
 * El bundle no usa `ext-soap`: el envelope se escribe a mano y se envía por POST.
 * Estas pruebas verifican que el mismo transporte funcione sobre un cliente
 * **PSR-18**, sin acoplarse a Symfony HttpClient.
 */
final class Psr18SoapTransportTest extends TestCase
{
    private const ENDPOINT = 'https://demo-facturacion.finkok.com/servicios/soap/utilities';

    private function transport(RecordingPsr18Client $client, float $timeout = 30.0): HttpClientSoapTransport
    {
        return new HttpClientSoapTransport(
            new Psr18RequestHandler($client, new Psr17Factory()),
            $timeout,
        );
    }

    public function testSendsSoapEnvelopeOverPsr18(): void
    {
        $client = new RecordingPsr18Client([Fixtures::content('datetime-success.xml')]);

        $response = $this->transport($client)->send($this->datetimeRequest());

        self::assertTrue($response->isSoapEnvelope());

        $request = $client->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::ENDPOINT, (string) $request->getUri());
        self::assertSame('text/xml; charset=utf-8', $request->getHeaderLine('Content-Type'));
        self::assertSame('"datetime"', $request->getHeaderLine('SOAPAction'));
    }

    public function testEnvelopeBodyIsWrittenByHandFromTheWsdl(): void
    {
        $client = new RecordingPsr18Client([Fixtures::content('datetime-success.xml')]);

        $this->transport($client)->send($this->datetimeRequest());

        $body = $client->lastBody();

        self::assertStringContainsString('soap:Envelope', $body);
        self::assertStringContainsString(SoapNamespaces::ENVELOPE, $body);
        self::assertStringContainsString('<tns:datetime', $body);
        self::assertStringContainsString(SoapNamespaces::UTILITIES, $body);
        self::assertStringContainsString('<tns:username>usuario</tns:username>', $body);
    }

    /**
     * El cuerpo debe llegar completo: el flujo se rebobina antes de enviarlo.
     */
    public function testBodyIsNotConsumedByPreviousReads(): void
    {
        $client = new RecordingPsr18Client([Fixtures::content('datetime-success.xml')]);

        $this->transport($client)->send($this->datetimeRequest());

        self::assertStringContainsString('</soap:Envelope>', $client->lastBody());
    }

    public function testHttpErrorWithoutEnvelopeBecomesTransportException(): void
    {
        $client = new RecordingPsr18Client([Fixtures::content('http-502.html')], [502]);

        try {
            $this->transport($client)->send($this->datetimeRequest());
            self::fail('Se esperaba una TransportException.');
        } catch (TransportException $exception) {
            self::assertSame(502, $exception->getHttpStatusCode());
            self::assertTrue($exception->isRetryable());
        }
    }

    public function testSoapFaultTakesPrecedenceOverHttpStatus(): void
    {
        $client = new RecordingPsr18Client([Fixtures::content('soap-fault.xml')], [500]);

        $this->expectException(\Estratos\FinkokUtilsBundle\Exception\SoapFaultException::class);

        $this->transport($client)->send($this->datetimeRequest());
    }

    public function testNonEnvelopeBodyBecomesUnexpectedResponse(): void
    {
        $client = new RecordingPsr18Client(['esto no es xml']);

        $this->expectException(UnexpectedResponseException::class);

        $this->transport($client)->send($this->datetimeRequest());
    }

    /**
     * Un cliente PSR-18 que falla debe traducirse a TransportException con el
     * endpoint y la operación en el mensaje.
     */
    public function testClientFailureBecomesTransportException(): void
    {
        $client = new class implements \Psr\Http\Client\ClientInterface {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                throw new class extends \RuntimeException implements \Psr\Http\Client\NetworkExceptionInterface {
                    public function getRequest(): \Psr\Http\Message\RequestInterface
                    {
                        return new \Nyholm\Psr7\Request('POST', 'https://example.test');
                    }
                };
            }
        };

        $transport = new HttpClientSoapTransport(
            new Psr18RequestHandler($client, new Psr17Factory()),
        );

        try {
            $transport->send($this->datetimeRequest());
            self::fail('Se esperaba una TransportException.');
        } catch (TransportException $exception) {
            self::assertSame('datetime', $exception->getOperation());
            self::assertSame(self::ENDPOINT, $exception->getEndpoint());
            self::assertTrue($exception->isRetryable(), 'Sin código HTTP se considera transitorio.');
        }
    }

    /**
     * El transporte por omisión (Symfony HttpClient) y el de PSR-18 producen el
     * mismo envelope: la lógica vive una sola vez.
     */
    public function testProducesTheSameEnvelopeAsTheDefaultHandler(): void
    {
        $psrClient = new RecordingPsr18Client([Fixtures::content('datetime-success.xml')]);
        $this->transport($psrClient)->send($this->datetimeRequest());

        $request = $this->datetimeRequest();

        // El envelope se serializa de forma determinista, así que comparamos el
        // cuerpo enviado por PSR-18 con el generado directamente por SoapRequest.
        self::assertSame($request->toXml(), $psrClient->lastBody());
    }

    private function datetimeRequest(): SoapRequest
    {
        return new SoapRequest(
            endpoint: self::ENDPOINT,
            operation: 'datetime',
            namespace: SoapNamespaces::UTILITIES,
            arguments: ['username' => 'usuario', 'password' => 'secreto'],
        );
    }
}

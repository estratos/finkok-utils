<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Soap;

use Estratos\FinkokUtilsBundle\Exception\TransportException;
use Estratos\FinkokUtilsBundle\Exception\UnexpectedResponseException;
use Estratos\FinkokUtilsBundle\Soap\HttpClientSoapTransport;
use Estratos\FinkokUtilsBundle\Soap\SoapNamespaces;
use Estratos\FinkokUtilsBundle\Soap\SoapRequest;
use Estratos\FinkokUtilsBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpClientSoapTransportTest extends TestCase
{
    private const ENDPOINT = 'https://demo-facturacion.finkok.com/servicios/soap/utilities';

    public function testSendsSoap11HeadersAndBody(): void
    {
        $captured = [];

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(Fixtures::content('datetime-success.xml'));
        });

        $transport = new HttpClientSoapTransport($client, 15.0);
        $response = $transport->send($this->datetimeRequest());

        self::assertSame('POST', $captured['method']);
        self::assertSame(self::ENDPOINT, $captured['url']);

        $headers = $this->normalizeHeaders($captured['options']['headers'] ?? []);
        self::assertSame('text/xml; charset=utf-8', $headers['content-type']);
        self::assertSame('"datetime"', $headers['soapaction']);
        self::assertSame(15.0, $captured['options']['timeout']);

        self::assertStringContainsString('<tns:datetime', $captured['options']['body']);
        self::assertTrue($response->isSoapEnvelope());
    }

    public function testMaskedPayloadWhenLoggingDisabled(): void
    {
        // `log_payloads` desactivado no debe alterar la petición enviada.
        $captured = [];

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = $options;

            return new MockResponse(Fixtures::content('datetime-success.xml'));
        });

        $transport = new HttpClientSoapTransport($client, logPayloads: false);
        $transport->send($this->datetimeRequest());

        self::assertArrayHasKey('body', $captured);
    }

    public function testThrowsTransportExceptionOnHttpErrorWithoutEnvelope(): void
    {
        $client = new MockHttpClient(
            new MockResponse(Fixtures::content('http-502.html'), ['http_code' => 502]),
        );

        $transport = new HttpClientSoapTransport($client);

        try {
            $transport->send($this->datetimeRequest());
            self::fail('Se esperaba una TransportException.');
        } catch (TransportException $exception) {
            self::assertSame(502, $exception->getHttpStatusCode());
            self::assertSame('datetime', $exception->getOperation());
            // Un 502 es transitorio, así que la política de reintentos lo cubre.
            self::assertTrue($exception->isRetryable());
        }
    }

    public function testSoapFaultTakesPrecedenceOverHttpStatus(): void
    {
        $client = new MockHttpClient(
            new MockResponse(Fixtures::content('soap-fault.xml'), ['http_code' => 500]),
        );

        $transport = new HttpClientSoapTransport($client);

        // El fault describe mejor el problema que el 500.
        $this->expectException(\Estratos\FinkokUtilsBundle\Exception\SoapFaultException::class);

        $transport->send($this->datetimeRequest());
    }

    public function testThrowsUnexpectedResponseWhenBodyIsNotAnEnvelope(): void
    {
        $client = new MockHttpClient(
            new MockResponse('<resultado>ok</resultado>', ['http_code' => 200]),
        );

        $transport = new HttpClientSoapTransport($client);

        $this->expectException(UnexpectedResponseException::class);

        $transport->send($this->datetimeRequest());
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

    /**
     * @param array<int|string, string> $headers
     *
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $header) {
            foreach (explode("\n", (string) $header) as $line) {
                $line = trim($line);
                if ('' === $line || !str_contains($line, ':')) {
                    continue;
                }

                [$name, $value] = explode(':', $line, 2);
                $normalized[strtolower(trim($name))] = trim($value);
            }
        }

        return $normalized;
    }
}

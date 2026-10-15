<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Psr18;

use Estratos\FinkokUtilsBundle\Exception\TransportException;
use Estratos\FinkokUtilsBundle\Soap\Http\HttpResponse;
use Estratos\FinkokUtilsBundle\Soap\Http\RequestHandlerInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Ejecutor del POST sobre cualquier cliente **PSR-18**.
 *
 * Permite usar el bundle con el cliente HTTP que ya tenga la aplicación (Guzzle,
 * `symfony/http-client` mediante un adaptador PSR-18, `php-http`, …) sin arrastrar
 * una implementación concreta y **sin `ext-soap`**.
 *
 * El `RequestFactoryInterface` (PSR-17) es necesario porque PSR-18 solo sabe
 * *enviar* peticiones, no construirlas.
 *
 * ## Sobre el timeout
 *
 * PSR-18 **no contempla timeouts**: es responsabilidad del cliente. El parámetro
 * `$timeout` se ignora deliberadamente aquí en lugar de traducirse a una cabecera
 * inventada, y se conserva en la firma porque forma parte del contrato común. Si
 * necesitas un timeout con este transporte, configúralo en tu cliente:
 *
 * ```php
 * new GuzzleHttp\Client(['timeout' => 30.0, 'connect_timeout' => 10.0]);
 * ```
 *
 * La alternativa con timeout garantizado por el bundle es el transporte por
 * omisión sobre Symfony HttpClient.
 */
final class Psr18RequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
    ) {
    }

    public function post(string $url, string $body, array $headers, float $timeout): HttpResponse
    {
        $request = $this->requestFactory->createRequest('POST', $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $request = $request->withBody($this->streamFor($body));

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            // El transporte añade el endpoint y la operación al mensaje final.
            throw new TransportException($exception->getMessage(), previous: $exception);
        }

        return new HttpResponse(
            $response->getStatusCode(),
            (string) $response->getBody(),
            $response->getHeaders(),
        );
    }

    /**
     * Crea el flujo con el envelope.
     *
     * PSR-17 (`RequestFactoryInterface`) no ofrece forma de crear flujos, así que se
     * envuelve un recurso temporal de PHP. Es una dependencia del lenguaje, no de
     * una implementación PSR-7 concreta, de modo que el bundle sigue sin acoplarse
     * a ninguna.
     */
    private function streamFor(string $body): StreamInterface
    {
        $resource = fopen('php://temp', 'r+');

        if (false === $resource) {
            throw new \RuntimeException('No fue posible abrir un flujo temporal para el envelope SOAP.');
        }

        fwrite($resource, $body);
        rewind($resource);

        return new Psr7Stream($resource);
    }
}

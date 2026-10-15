<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Http;

use Estratos\FinkokUtilsBundle\Exception\TransportException as FinkokTransportException;
use Symfony\Component\HttpClient\Exception\TransportExceptionInterface as HttpClientTransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ejecutor del POST sobre Symfony HttpClient (el que usa el bundle por omisión).
 *
 * Symfony HttpClient no es un cliente PSR-18, de modo que este adaptador es el que
 * permite que la aplicación comparta su pool de conexiones, proxy y CA cuando ya
 * tiene el servicio `http_client`.
 */
final class SymfonyRequestHandler implements RequestHandlerInterface
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function post(string $url, string $body, array $headers, float $timeout): HttpResponse
    {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
            ]);

            return new HttpResponse(
                $response->getStatusCode(),
                $response->getContent(false),
                $response->getHeaders(false),
            );
        } catch (HttpClientTransportExceptionInterface $exception) {
            // El transporte añade el endpoint y la operación al mensaje final.
            throw new FinkokTransportException(
                $exception->getMessage(),
                previous: $exception,
            );
        }
    }
}

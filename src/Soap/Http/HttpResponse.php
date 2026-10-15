<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Http;

/**
 * Respuesta HTTP cruda ya normalizada.
 *
 * Es el único contrato que el transporte SOAP necesita, y es intencionadamente
 * agnóstico: ni `Symfony\Contracts\HttpClient\ResponseInterface` ni
 * `Psr\Http\Message\ResponseInterface`, para que ambos transportes compartan el
 * mismo camino de interpretación del envelope.
 */
final class HttpResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }
}

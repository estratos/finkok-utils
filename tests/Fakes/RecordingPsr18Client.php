<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Fakes;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Cliente PSR-18 de prueba: registra la petición y devuelve la respuesta encolada.
 *
 * Permite verificar el contrato PSR-18 del bundle (método, URL, cabeceras SOAP y
 * cuerpo del envelope) sin abrir conexiones.
 */
final class RecordingPsr18Client implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<string> */
    private array $bodies;

    /** @var list<int> */
    private array $statusCodes;

    /**
     * @param list<string> $bodies      cuerpos de respuesta, en orden
     * @param list<int>    $statusCodes códigos HTTP correspondientes
     */
    public function __construct(array $bodies = [], array $statusCodes = [])
    {
        $this->bodies = array_values($bodies);
        $this->statusCodes = array_values($statusCodes);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ([] === $this->bodies) {
            throw new \RuntimeException('El cliente PSR-18 de prueba no tiene respuestas encoladas.');
        }

        $index = min(\count($this->requests) - 1, \count($this->bodies) - 1);

        return new Response(
            $this->statusCodes[$index] ?? 200,
            ['Content-Type' => 'text/xml; charset=utf-8'],
            $this->bodies[$index],
        );
    }

    public function lastRequest(): RequestInterface
    {
        if ([] === $this->requests) {
            throw new \RuntimeException('No se envió ninguna petición PSR-18.');
        }

        return $this->requests[array_key_last($this->requests)];
    }

    /**
     * Cuerpo de la última petición, leído del flujo real.
     */
    public function lastBody(): string
    {
        $body = (string) $this->lastRequest()->getBody();

        return $body;
    }

    public function callCount(): int
    {
        return \count($this->requests);
    }
}

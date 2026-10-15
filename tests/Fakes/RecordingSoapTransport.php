<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Fakes;

use Estratos\FinkokUtilsBundle\Soap\SoapRequest;
use Estratos\FinkokUtilsBundle\Soap\SoapResponse;
use Estratos\FinkokUtilsBundle\Soap\SoapTransportInterface;

/**
 * Transporte de prueba que no abre conexiones.
 *
 * Registra cada petición recibida para poder afirmar sobre la operación, el
 * namespace y los argumentos exactos que el servicio habría enviado a Finkok.
 * Esta es la garantía central de la suite: el contrato con el WSDL se verifica sin
 * depender de que el PAC esté disponible.
 */
final class RecordingSoapTransport implements SoapTransportInterface
{
    /** @var list<SoapRequest> */
    public array $requests = [];

    /** @var list<string> */
    private array $responses;

    /** @var list<int> */
    private array $statusCodes = [];

    /**
     * @param list<string> $responses cuerpos de respuesta, en orden; el último se reutiliza
     * @param list<int>    $statusCodes códigos HTTP correspondientes
     */
    public function __construct(array $responses = [], array $statusCodes = [])
    {
        $this->responses = array_values($responses);
        $this->statusCodes = array_values($statusCodes);
    }

    /**
     * Encola una respuesta adicional.
     */
    public function pushResponse(string $body, int $statusCode = 200): void
    {
        $this->responses[] = $body;
        $this->statusCodes[] = $statusCode;
    }

    public function send(SoapRequest $request): SoapResponse
    {
        $this->requests[] = $request;

        if ([] === $this->responses) {
            throw new \RuntimeException(sprintf(
                'El transporte de prueba no tiene respuestas encoladas para la operación "%s".',
                $request->operation,
            ));
        }

        // El último cuerpo encolado se reutiliza para las peticiones sobrantes.
        $index = min(\count($this->requests) - 1, \count($this->responses) - 1);

        return new SoapResponse($this->responses[$index], $this->statusCodes[$index] ?? 200);
    }

    /**
     * Última petición enviada.
     */
    public function lastRequest(): SoapRequest
    {
        if ([] === $this->requests) {
            throw new \RuntimeException('No se envió ninguna petición.');
        }

        return $this->requests[array_key_last($this->requests)];
    }

    public function callCount(): int
    {
        return \count($this->requests);
    }

    /**
     * Argumentos de la última petición, leídos del envelope realmente generado.
     *
     * Se leen del DOM —y no de `SoapRequest::$arguments`— para que las pruebas
     * verifiquen el XML que viajaría por la red, incluidos los valores omitidos por
     * ser nulos o vacíos.
     *
     * @return array<string, string|null>
     */
    public function lastArguments(): array
    {
        $document = new \DOMDocument();
        $document->loadXML($this->lastRequest()->toXml());

        $body = $document->getElementsByTagNameNS(
            'http://schemas.xmlsoap.org/soap/envelope/',
            'Body',
        )->item(0);

        if (!$body instanceof \DOMElement) {
            throw new \RuntimeException('El envelope generado no contiene un Body.');
        }

        $operation = null;
        foreach ($body->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $operation = $child;
                break;
            }
        }

        if (!$operation instanceof \DOMElement) {
            throw new \RuntimeException('El envelope generado no contiene la operación.');
        }

        $arguments = [];
        foreach ($operation->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $arguments[$child->localName] = trim($child->textContent);
            }
        }

        return $arguments;
    }
}

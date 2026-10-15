<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap;

use Estratos\FinkokUtilsBundle\Xml\DomReader;

/**
 * SOAP Fault a nivel de protocolo (`soap:Fault`).
 *
 * Indica que la petición no llegó a ejecutarse como operación de negocio: error
 * de esquema, operación inexistente, URL equivocada, etc. No debe confundirse con
 * los errores de negocio de Finkok, que viajan en el nodo `error` de cada
 * resultado (`<error>…</error>`) dentro de una respuesta correcta.
 */
final class SoapFault
{
    public function __construct(
        public readonly ?string $code = null,
        public readonly ?string $message = null,
        public readonly ?string $detail = null,
    ) {
    }

    public static function fromElement(\DOMElement $fault): self
    {
        return new self(
            DomReader::textOf($fault, 'faultcode'),
            DomReader::textOf($fault, 'faultstring'),
            DomReader::textOf($fault, 'detail'),
        );
    }

    /**
     * Indica si el fallo proviene del cliente (petición mal formada) según la
     * convención SOAP 1.1 (`soap:Client`, `soapenv:Client`, `Client`).
     */
    public function isClientFault(): bool
    {
        return null !== $this->code && str_contains(strtolower($this->code), 'client');
    }

    public function describe(): string
    {
        return sprintf('[%s] %s', $this->code ?? 'sin faultcode', $this->message ?? 'sin faultstring');
    }

    public function __toString(): string
    {
        return $this->describe();
    }
}

<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Value;

use Estratos\FinkokUtilsBundle\Soap\SoapValueInterface;
use Estratos\FinkokUtilsBundle\Soap\SoapWriter;

/**
 * Contenido que **ya** viene codificado en base64 y no debe codificarse otra vez.
 *
 * Evita la doble codificación, que en los servicios de Finkok provoca la
 * incidencia 704 («El certificado es inválido»).
 */
final class Base64EncodedValue implements SoapValueInterface
{
    private function __construct(private readonly string $encoded)
    {
    }

    public static function fromEncoded(string $encoded): self
    {
        return new self($encoded);
    }

    public function encoded(): string
    {
        return $this->encoded;
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): void
    {
        $writer->textElement($parent, $name, $namespace, $this->encoded);
    }
}

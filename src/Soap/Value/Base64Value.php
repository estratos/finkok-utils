<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Value;

use Estratos\FinkokUtilsBundle\Soap\SoapValueInterface;
use Estratos\FinkokUtilsBundle\Soap\SoapWriter;

/**
 * Contenido binario que debe viajar codificado en base64.
 *
 * El WSDL de utilerías no declara campos `xs:base64Binary` (el más cercano es el
 * `xml` de `get_xml`, que es `xs:string`), pero se conserva la primitiva por
 * simetría con el resto de los servicios de Finkok.
 */
final class Base64Value implements SoapValueInterface
{
    private function __construct(private readonly string $binary)
    {
    }

    public static function fromBinary(string $binary): self
    {
        return new self($binary);
    }

    public function binary(): string
    {
        return $this->binary;
    }

    public function encoded(): string
    {
        return base64_encode($this->binary);
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): void
    {
        $writer->textElement($parent, $name, $namespace, $this->encoded());
    }
}

<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Value;

use Estratos\FinkokUtilsBundle\Soap\SoapValueInterface;
use Estratos\FinkokUtilsBundle\Soap\SoapWriter;

/**
 * Elemento cuyos datos viajan en atributos en lugar de nodos hijos.
 *
 * Algunos tipos de Finkok (por ejemplo `UUID` en cancelación) se serializan con
 * atributos. El WSDL de utilerías no los usa hoy, pero se mantiene la primitiva
 * para no tener que reescribir el envelope si Finkok los introduce.
 */
final class AttributeElement implements SoapValueInterface
{
    /**
     * @param array<string, string|null> $attributes
     */
    public function __construct(private readonly array $attributes = [])
    {
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): void
    {
        $element = $writer->element($parent, $name, $namespace);

        foreach ($this->attributes as $attributeName => $attributeValue) {
            if (null === $attributeValue || '' === trim($attributeValue)) {
                continue;
            }

            $writer->attribute($element, $attributeName, $attributeValue);
        }
    }
}

<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Value;

use Estratos\FinkokUtilsBundle\Soap\SoapValueInterface;
use Estratos\FinkokUtilsBundle\Soap\SoapWriter;

/**
 * Valor compuesto: un elemento con hijos nombrados.
 *
 * Se usa para los tipos complejos del WSDL, por ejemplo:
 *
 * ```xml
 * <tns:report_credit>
 *   <tns:username>…</tns:username>
 * </tns:report_credit>
 * ```
 *
 * El namespace de los hijos puede diferir del namespace del contenedor, que es
 * justo lo que ocurre en `report_creditResult/result/ReportTotalCreditArray`.
 */
final class ComplexValue implements SoapValueInterface
{
    /**
     * @param array<string, mixed> $children mapa nombre => valor (escalar o SoapValueInterface)
     * @param string|null          $childrenNamespace namespace de los hijos; `null` usa el del contenedor
     */
    public function __construct(
        private readonly array $children,
        private readonly ?string $childrenNamespace = null,
    ) {
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): void
    {
        $element = $writer->element($parent, $name, $namespace);
        $childNamespace = $this->childrenNamespace ?? $namespace;

        foreach (Value::map($this->children) as $childName => $childValue) {
            $childValue->write($writer, $element, $childName, $childNamespace);
        }
    }
}

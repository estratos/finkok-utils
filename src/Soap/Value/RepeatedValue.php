<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Value;

use Estratos\FinkokUtilsBundle\Soap\SoapValueInterface;
use Estratos\FinkokUtilsBundle\Soap\SoapWriter;

/**
 * Valor repetido: un elemento contenedor con N hijos del mismo nombre.
 *
 * El WSDL de utilerías declara sus arreglos como un contenedor con `maxOccurs`
 * ilimitado, por ejemplo:
 *
 * ```xml
 * <s0:invoices>
 *   <s0:ReportUUID>…</s0:ReportUUID>
 *   <s0:ReportUUID>…</s0:ReportUUID>
 * </s0:invoices>
 * ```
 */
final class RepeatedValue implements SoapValueInterface
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        private readonly string $itemName,
        private readonly array $items,
        private readonly ?string $itemNamespace = null,
    ) {
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): void
    {
        $element = $writer->element($parent, $name, $namespace);
        $itemNamespace = $this->itemNamespace ?? $namespace;

        foreach ($this->items as $item) {
            $value = Value::of($item);

            if (null === $value) {
                continue;
            }

            $value->write($writer, $element, $this->itemName, $itemNamespace);
        }
    }
}

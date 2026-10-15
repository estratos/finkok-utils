<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap;

/**
 * Valor que sabe escribirse dentro de un envelope SOAP.
 *
 * Existe para que los servicios declaren sus argumentos de forma natural y para
 * poder representar las estructuras complejas y los arreglos repetidos que
 * declara el WSDL (`s0:ReportTotalCreditArray`, `s0:ReportUUIDArray`, …).
 */
interface SoapValueInterface
{
    /**
     * Escribe el valor como hijo `$name` del elemento `$parent`.
     */
    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): void;
}

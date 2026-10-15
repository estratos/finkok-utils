<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap;

/**
 * Namespaces XML usados por el Web Service de utilerías de Finkok.
 *
 * `utilities.wsdl` es document/literal con `elementFormDefault="qualified"` y
 * declara **dos** esquemas con namespaces distintos:
 *
 *  - el esquema del servicio (`http://facturacion.finkok.com/utilities`), donde
 *    viven los elementos de entrada y salida de cada operación (`datetime`,
 *    `get_xml`, `report_total`, …);
 *  - el esquema compartido `apps.services.soap.core.views`, donde viven los tipos
 *    de resultado (`DateResult`, `XMLResult`, `ReportUUIDResult`, `IdCCPResult`,
 *    `MonedaResult`, `ReportTotalCredit`, …).
 *
 * Cada elemento debe emitirse en el namespace donde fue declarado. Confundirlos
 * produce respuestas vacías o la incidencia 738 («Errores con schemaLocations,
 * namespaces y prefijos»).
 */
final class SoapNamespaces
{
    /** Envelope SOAP 1.1. */
    public const ENVELOPE = 'http://schemas.xmlsoap.org/soap/envelope/';

    /** Instancia de esquema XML, para atributos como `xsi:nil`. */
    public const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    /** Esquema XML, para atributos como `xsi:type`. */
    public const XSD = 'http://www.w3.org/2001/XMLSchema';

    /**
     * `targetNamespace` del servicio de utilerías.
     */
    public const UTILITIES = 'http://facturacion.finkok.com/utilities';

    /**
     * Namespace de los tipos compartidos por todos los servicios de Finkok.
     */
    public const VIEWS = 'apps.services.soap.core.views';

    private function __construct()
    {
    }
}

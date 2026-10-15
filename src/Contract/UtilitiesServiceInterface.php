<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Contract;

use Estratos\FinkokUtilsBundle\Config\CredentialsInterface;
use Estratos\FinkokUtilsBundle\Model\DateRange;
use Estratos\FinkokUtilsBundle\Model\DateTimeResult;
use Estratos\FinkokUtilsBundle\Model\DownloadXmlResult;
use Estratos\FinkokUtilsBundle\Model\IdCcpResult;
use Estratos\FinkokUtilsBundle\Model\InvoiceType;
use Estratos\FinkokUtilsBundle\Model\MonedaResult;
use Estratos\FinkokUtilsBundle\Model\ReportCreditResult;
use Estratos\FinkokUtilsBundle\Model\ReportTotalResult;
use Estratos\FinkokUtilsBundle\Model\ReportUuidResult;

/**
 * Adaptador del Web Service de utilerías de Finkok (`utilities.wsdl`).
 *
 * Agrupa las siete operaciones que expone el WSDL:
 *
 * | Operación        | Método                    | Para qué sirve                                   |
 * |------------------|---------------------------|--------------------------------------------------|
 * | `datetime`       | {@see datetime()}         | Hora oficial del PAC (evita CFDI40147)           |
 * | `get_xml`        | {@see downloadXml()}      | Recupera un CFDI timbrado por UUID (3 meses)     |
 * | `report_credit`  | {@see reportCredit()}     | Créditos añadidos a la cuenta                    |
 * | `report_total`   | {@see reportTotal()}      | Comprobantes timbrados en un rango               |
 * | `report_uuid`    | {@see reportUuid()}       | Folios fiscales emitidos en un rango             |
 * | `get_idccp`      | {@see getIdCcp()}         | Identificador del complemento Carta Porte        |
 * | `get_moneda_usd` | {@see getMonedaUsd()}     | Tipo de cambio del dólar                         |
 *
 * Todas las llamadas aceptan un perfil de credenciales explícito; si se omite se
 * usa el perfil por defecto del proveedor configurado. Pasar el perfil
 * explícitamente es lo recomendado en aplicaciones multi-RFC y en workers de larga
 * vida (Messenger, RoadRunner), donde el estado global es peligroso.
 */
interface UtilitiesServiceInterface
{
    /**
     * `datetime`: fecha y hora de los servidores de Finkok.
     *
     * @param string|null $zipcode código postal para el desfase horario local
     *                             (el WSDL exige el patrón `[0-9]{5}`)
     */
    public function datetime(
        ?string $zipcode = null,
        ?CredentialsInterface $credentials = null,
    ): DateTimeResult;

    /**
     * `get_xml`: descarga un CFDI timbrado usando su Folio Fiscal.
     *
     * Finkok conserva el XML de los **últimos 3 meses**. Verifica
     * {@see DownloadXmlResult::looksLikePath()} antes de tratar el valor como CFDI.
     *
     * @param string           $uuid        Folio Fiscal del comprobante
     * @param string|null      $taxpayerId   RFC emisor; si se omite se usa el del perfil
     * @param InvoiceType|null $invoiceType tipo de comprobante; por omisión `Ingreso`
     */
    public function downloadXml(
        string $uuid,
        ?string $taxpayerId = null,
        ?InvoiceType $invoiceType = null,
        ?CredentialsInterface $credentials = null,
    ): DownloadXmlResult;

    /**
     * `report_credit`: créditos añadidos a la cuenta del RFC.
     *
     * El WSDL no acepta rango de fechas en esta operación: Finkok devuelve todos
     * los movimientos registrados.
     *
     * @param string|null $taxpayerId RFC a consultar; si se omite se usa el del perfil
     */
    public function reportCredit(
        ?string $taxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): ReportCreditResult;

    /**
     * `report_total`: número de comprobantes timbrados por RFC dentro de un rango.
     *
     * @param DateRange   $range      rango de fechas (obligatorio en el WSDL)
     * @param string|null $taxpayerId RFC a consultar; si se omite se usa el del perfil
     */
    public function reportTotal(
        DateRange $range,
        ?string $taxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): ReportTotalResult;

    /**
     * `report_uuid`: Folios Fiscales emitidos por un RFC dentro de un rango.
     *
     * @param DateRange   $range      rango de fechas (obligatorio en el WSDL)
     * @param string|null $taxpayerId RFC a consultar; si se omite se usa el del perfil
     */
    public function reportUuid(
        DateRange $range,
        ?string $taxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): ReportUuidResult;

    /**
     * `get_idccp`: identificador del complemento Carta Porte.
     */
    public function getIdCcp(?CredentialsInterface $credentials = null): IdCcpResult;

    /**
     * `get_moneda_usd`: tipo de cambio del dólar que aplica Finkok.
     */
    public function getMonedaUsd(
        ?string $date = null,
        ?CredentialsInterface $credentials = null,
    ): MonedaResult;
}

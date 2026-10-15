<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Hydrator;

use Estratos\FinkokUtilsBundle\Model\ReportUuidItem;
use Estratos\FinkokUtilsBundle\Model\ReportUuidResult;
use Estratos\FinkokUtilsBundle\Xml\DomReader;

/**
 * Construye un {@see ReportUuidResult} desde el resultado de `report_uuid`.
 *
 * Forma declarada por el WSDL:
 *
 * ```xml
 * <report_uuidResult>
 *   <invoices>
 *     <ReportUUID><uuid>…</uuid><date>…</date></ReportUUID>
 *     …
 *   </invoices>
 *   <error>…</error>
 * </report_uuidResult>
 * ```
 *
 * El parser es deliberadamente tolerante porque las respuestas reales no siempre
 * coinciden con el esquema: se acepta que el contenedor se llame `invoices` o
 * `result`, que los `ReportUUID` cuelguen directamente del resultado, y que cada
 * fila use `rtaxpayer_id` o `receiver_taxpayer_id`.
 */
final class ReportUuidHydrator
{
    private function __construct()
    {
    }

    public static function hydrate(?\DOMElement $result, ?string $rawResponse = null): ReportUuidResult
    {
        if (null === $result) {
            return new ReportUuidResult([], null, $rawResponse);
        }

        $invoices = [];

        foreach (self::collectInvoiceElements($result) as $element) {
            $invoices[] = new ReportUuidItem(
                uuid: DomReader::textOf($element, 'uuid'),
                date: DomReader::textOf($element, 'date'),
                taxpayerId: DomReader::textOf($element, 'taxpayer_id'),
                receiverTaxpayerId: DomReader::textOf($element, 'rtaxpayer_id')
                    ?? DomReader::textOf($element, 'receiver_taxpayer_id'),
                total: DomReader::textOf($element, 'total'),
            );
        }

        return new ReportUuidResult(
            invoices: $invoices,
            error: DomReader::textOf($result, 'error'),
            rawResponse: $rawResponse,
        );
    }

    /**
     * Localiza los elementos `ReportUUID` sea cual sea el contenedor.
     *
     * @return list<\DOMElement>
     */
    private static function collectInvoiceElements(\DOMElement $result): array
    {
        foreach (['invoices', 'result'] as $containerName) {
            $container = DomReader::firstElement($result, $containerName);

            if (null === $container) {
                continue;
            }

            $items = DomReader::elements($container, 'ReportUUID');

            if ([] !== $items) {
                return $items;
            }
        }

        // Respuesta plana: los ReportUUID cuelgan del propio resultado.
        $direct = DomReader::elements($result, 'ReportUUID');

        if ([] !== $direct) {
            return $direct;
        }

        // Último recurso: buscarlos en cualquier profundidad.
        return DomReader::descendants($result, 'ReportUUID');
    }
}

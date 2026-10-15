<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Hydrator;

use Estratos\FinkokUtilsBundle\Model\ReportCreditItem;
use Estratos\FinkokUtilsBundle\Model\ReportCreditResult;
use Estratos\FinkokUtilsBundle\Xml\DomReader;

/**
 * Construye un {@see ReportCreditResult} desde el resultado de `report_credit`.
 *
 * Forma declarada por el WSDL, y confirmada por el ejemplo oficial de Finkok:
 *
 * ```xml
 * <tns:report_creditResult>
 *   <s0:result>
 *     <s0:ReportTotalCredit>
 *       <s0:credit>3</s0:credit>
 *       <s0:date>2024-08-26T17:43:50.739288</s0:date>
 *     </s0:ReportTotalCredit>
 *   </s0:result>
 * </tns:report_creditResult>
 * ```
 *
 * El hidratador distingue la **presencia** del contenedor `result`, porque es la
 * única señal de que la cuenta es ilimitada:
 *
 *  - `<result/>` presente y vacío → cuenta de prepago sin movimientos;
 *  - `result` ausente → cuenta ilimitada.
 *
 * La fecha puede traer **seis** decimales de fracción de segundo; el modelo la
 * conserva como texto y la interpreta de forma tolerante en
 * {@see ReportCreditItem::dateTime()}.
 */
final class ReportCreditHydrator
{
    private function __construct()
    {
    }

    public static function hydrate(?\DOMElement $result, ?string $rawResponse = null): ReportCreditResult
    {
        if (null === $result) {
            // Sin elemento de resultado no hay señal alguna sobre el tipo de cuenta.
            return new ReportCreditResult([], null, $rawResponse, null);
        }

        $container = DomReader::firstElement($result, 'result');

        // La ausencia del nodo `result` significa cuenta ilimitada. Cuando el nodo
        // existe —aunque venga sin hijos— la cuenta es de prepago.
        $unlimited = null === $container;

        $items = [];

        foreach (self::collectCreditElements($result) as $element) {
            $items[] = new ReportCreditItem(
                credit: DomReader::textOf($element, 'credit'),
                date: DomReader::textOf($element, 'date'),
                taxpayerId: DomReader::textOf($element, 'taxpayer_id'),
            );
        }

        return new ReportCreditResult(
            items: $items,
            error: DomReader::textOf($result, 'error'),
            rawResponse: $rawResponse,
            unlimited: $unlimited,
        );
    }

    /**
     * @return list<\DOMElement>
     */
    private static function collectCreditElements(\DOMElement $result): array
    {
        $container = DomReader::firstElement($result, 'result');

        if (null !== $container) {
            $items = DomReader::elements($container, 'ReportTotalCredit');

            if ([] !== $items) {
                return $items;
            }
        }

        $direct = DomReader::elements($result, 'ReportTotalCredit');

        if ([] !== $direct) {
            return $direct;
        }

        return DomReader::descendants($result, 'ReportTotalCredit');
    }
}

<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Hydrator;

use Estratos\FinkokUtilsBundle\Model\ReportCreditItem;
use Estratos\FinkokUtilsBundle\Model\ReportCreditResult;
use Estratos\FinkokUtilsBundle\Xml\DomReader;

/**
 * Construye un {@see ReportCreditResult} desde el resultado de `report_credit`.
 *
 * Forma declarada por el WSDL:
 *
 * ```xml
 * <report_creditResult>
 *   <result>
 *     <ReportTotalCredit>
 *       <taxpayer_id>EKU9003173C9</taxpayer_id>
 *       <credit>100</credit>
 *       <date>2019-01-13T14:15:16.171819</date>
 *     </ReportTotalCredit>
 *   </result>
 *   <error>…</error>
 * </report_creditResult>
 * ```
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
            return new ReportCreditResult([], null, $rawResponse);
        }

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

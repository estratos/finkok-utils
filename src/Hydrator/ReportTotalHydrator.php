<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Hydrator;

use Estratos\FinkokUtilsBundle\Model\ReportTotalItem;
use Estratos\FinkokUtilsBundle\Model\ReportTotalResult;
use Estratos\FinkokUtilsBundle\Xml\DomReader;

/**
 * Construye un {@see ReportTotalResult} desde el resultado de `report_total`.
 *
 * Forma declarada por el WSDL:
 *
 * ```xml
 * <report_totalResult>
 *   <result>
 *     <ReportTotal><total>123</total><taxpayer_id>EKU9003173C9</taxpayer_id></ReportTotal>
 *   </result>
 *   <error>…</error>
 * </report_totalResult>
 * ```
 *
 * Cuando el periodo no tiene timbres, Finkok responde `result` presente pero
 * **vacío** (sin ningún `ReportTotal`), de modo que un resultado correcto con cero
 * filas es normal y no debe tratarse como error.
 */
final class ReportTotalHydrator
{
    private function __construct()
    {
    }

    public static function hydrate(?\DOMElement $result, ?string $rawResponse = null): ReportTotalResult
    {
        if (null === $result) {
            return new ReportTotalResult([], null, $rawResponse);
        }

        $items = [];

        foreach (self::collectTotalElements($result) as $element) {
            $items[] = new ReportTotalItem(
                total: DomReader::textOf($element, 'total'),
                taxpayerId: DomReader::textOf($element, 'taxpayer_id'),
            );
        }

        return new ReportTotalResult(
            items: $items,
            error: DomReader::textOf($result, 'error'),
            rawResponse: $rawResponse,
        );
    }

    /**
     * @return list<\DOMElement>
     */
    private static function collectTotalElements(\DOMElement $result): array
    {
        $container = DomReader::firstElement($result, 'result');

        if (null !== $container) {
            $items = DomReader::elements($container, 'ReportTotal');

            if ([] !== $items) {
                return $items;
            }
        }

        $direct = DomReader::elements($result, 'ReportTotal');

        if ([] !== $direct) {
            return $direct;
        }

        return DomReader::descendants($result, 'ReportTotal');
    }
}

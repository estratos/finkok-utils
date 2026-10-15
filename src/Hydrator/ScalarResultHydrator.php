<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Hydrator;

use Estratos\FinkokUtilsBundle\Model\DateTimeResult;
use Estratos\FinkokUtilsBundle\Model\DownloadXmlResult;
use Estratos\FinkokUtilsBundle\Model\IdCcpResult;
use Estratos\FinkokUtilsBundle\Model\MonedaResult;
use Estratos\FinkokUtilsBundle\Xml\DomReader;

/**
 * Construye los resultados escalares de utilerías: `datetime`, `get_xml`,
 * `get_idccp` y `get_moneda_usd`.
 *
 * Estas cuatro operaciones comparten la misma forma —un nodo de valor y un nodo
 * `error`— así que se resuelven con un solo hidratador en lugar de cuatro clases
 * casi idénticas.
 *
 * Todos los hidratadores aceptan `null` como resultado (Body vacío) y devuelven
 * un DTO vacío en lugar de fallar: la ausencia de datos es información válida y el
 * consumidor decide con `isSuccess()`.
 */
final class ScalarResultHydrator
{
    private function __construct()
    {
    }

    public static function datetime(?\DOMElement $result, ?string $rawResponse = null): DateTimeResult
    {
        return new DateTimeResult(
            datetime: self::value($result, 'datetime'),
            error: self::error($result),
            rawResponse: $rawResponse,
        );
    }

    /**
     * El `xml` se lee con {@see DomReader::xmlText()} porque `get_xml` entrega el
     * CFDI escapado dentro del envelope.
     */
    public static function downloadXml(?\DOMElement $result, ?string $rawResponse = null): DownloadXmlResult
    {
        $xml = null;

        if (null !== $result) {
            $node = DomReader::firstElement($result, 'xml');

            if (null !== $node) {
                $xml = DomReader::xmlText($node);
            }
        }

        return new DownloadXmlResult(
            xml: $xml,
            error: self::error($result),
            rawResponse: $rawResponse,
        );
    }

    public static function idCcp(?\DOMElement $result, ?string $rawResponse = null): IdCcpResult
    {
        return new IdCcpResult(
            idccp: self::value($result, 'idccp') ?? self::value($result, 'idCCP'),
            error: self::error($result),
            rawResponse: $rawResponse,
        );
    }

    public static function moneda(?\DOMElement $result, ?string $rawResponse = null): MonedaResult
    {
        return new MonedaResult(
            moneda: self::value($result, 'moneda'),
            error: self::error($result),
            rawResponse: $rawResponse,
        );
    }

    private static function value(?\DOMElement $result, string $localName): ?string
    {
        return null === $result ? null : DomReader::textOf($result, $localName);
    }

    private static function error(?\DOMElement $result): ?string
    {
        return null === $result ? null : DomReader::textOf($result, 'error');
    }
}

<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Una fila del reporte `report_total`.
 *
 * Corresponde al tipo `s0:ReportTotal` (`total`, `taxpayer_id`) y al arreglo
 * `report_totalResult/result/ReportTotal`.
 *
 * El campo `total` está declarado como `xs:integer` en el WSDL y representa el
 * **número de comprobantes timbrados** en el rango consultado, no un importe
 * monetario.
 */
final class ReportTotalItem
{
    public function __construct(
        /** Número de comprobantes timbrados en el periodo. */
        public readonly ?string $total = null,
        /** RFC al que corresponde el conteo. */
        public readonly ?string $taxpayerId = null,
    ) {
    }

    /**
     * Número de comprobantes como entero, o `null` si no es interpretable.
     */
    public function totalAsInt(): ?int
    {
        if (null === $this->total || !is_numeric(trim($this->total))) {
            return null;
        }

        return (int) trim($this->total);
    }

    /**
     * @return array{total: string|null, taxpayer_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'taxpayer_id' => $this->taxpayerId,
        ];
    }
}

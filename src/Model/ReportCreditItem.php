<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Un movimiento de créditos del reporte `report_credit`.
 *
 * Corresponde al tipo `s0:ReportTotalCredit` del WSDL y a una fila del arreglo
 * `report_creditResult/result/ReportTotalCredit`.
 */
final class ReportCreditItem
{
    public function __construct(
        /**
         * Créditos añadidos en el movimiento. El WSDL lo declara `xs:integer`;
         * se conserva como texto porque Finkok ocasionalmente devuelve valores
         * fuera del rango de un entero de 64 bits.
         */
        public readonly ?string $credit = null,
        /** Fecha del movimiento (`xs:dateTime`), tal cual la devuelve Finkok. */
        public readonly ?string $date = null,
        /** RFC al que corresponde el movimiento, si el servidor lo incluye. */
        public readonly ?string $taxpayerId = null,
    ) {
    }

    /**
     * Créditos como número, o `null` si no es interpretable.
     */
    public function creditAsInt(): ?int
    {
        if (null === $this->credit || !is_numeric(trim($this->credit))) {
            return null;
        }

        return (int) trim($this->credit);
    }

    /**
     * Fecha del movimiento como objeto, o `null`.
     */
    public function dateTime(\DateTimeZone|string|null $timeZone = null): ?\DateTimeImmutable
    {
        return ModelDate::parse($this->date, $timeZone);
    }

    /**
     * @return array{credit: string|null, date: string|null, taxpayer_id: string|null}
     */
    public function toArray(): array
    {
        return [
            'credit' => $this->credit,
            'date' => $this->date,
            'taxpayer_id' => $this->taxpayerId,
        ];
    }
}

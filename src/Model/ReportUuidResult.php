<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Resultado de `report_uuid`: los comprobantes emitidos por un RFC en un rango.
 *
 * El WSDL coloca el arreglo `ReportUUID` dentro de
 * `report_uuidResult/invoices`. Como el rango se expresa en
 * `date_from`/`date_to`, este reporte es la forma canónica de conciliar qué CFDIs
 * se timbraron en un periodo.
 */
final class ReportUuidResult extends UtilitiesResult
{
    /**
     * @param list<ReportUuidItem> $invoices
     */
    public function __construct(
        private readonly array $invoices = [],
        ?string $error = null,
        ?string $rawResponse = null,
    ) {
        parent::__construct($error, $rawResponse);
    }

    /**
     * Comprobantes del reporte, en el orden en que los devolvió Finkok.
     *
     * @return list<ReportUuidItem>
     */
    public function invoices(): array
    {
        return $this->invoices;
    }

    /**
     * Alias de {@see invoices()}.
     *
     * @return list<ReportUuidItem>
     */
    public function items(): array
    {
        return $this->invoices;
    }

    /**
     * Solo los Folios Fiscales (UUID) del reporte.
     *
     * @return list<string>
     */
    public function uuids(): array
    {
        $uuids = [];

        foreach ($this->invoices as $invoice) {
            if (null !== $invoice->uuid && '' !== trim($invoice->uuid)) {
                $uuids[] = $invoice->uuid;
            }
        }

        return $uuids;
    }

    public function isEmpty(): bool
    {
        return [] === $this->invoices;
    }

    public function count(): int
    {
        return \count($this->invoices);
    }

    /**
     * @return list<array{uuid: string|null, date: string|null, taxpayer_id: string|null, rtaxpayer_id: string|null, total: string|null}>
     */
    public function toArray(): array
    {
        return array_map(static fn (ReportUuidItem $item): array => $item->toArray(), $this->invoices);
    }

    public function isSuccess(): bool
    {
        return !$this->isError();
    }
}

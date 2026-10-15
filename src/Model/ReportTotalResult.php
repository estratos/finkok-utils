<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Resultado de `report_total`: comprobantes timbrados por RFC en un periodo.
 *
 * El WSDL devuelve un arreglo `ReportTotal` dentro de
 * `report_totalResult/result`. En la práctica Finkok responde **una sola fila**
 * con el conteo del RFC consultado, pero el esquema permite varias, así que se
 * exponen como lista.
 */
final class ReportTotalResult extends UtilitiesResult
{
    /**
     * @param list<ReportTotalItem> $items
     */
    public function __construct(
        private readonly array $items = [],
        ?string $error = null,
        ?string $rawResponse = null,
    ) {
        parent::__construct($error, $rawResponse);
    }

    /**
     * @return list<ReportTotalItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Primera fila del reporte, o `null` si no hay ninguna.
     */
    public function first(): ?ReportTotalItem
    {
        return $this->items[0] ?? null;
    }

    /**
     * Número de comprobantes de la primera fila, que es el dato útil habitual.
     *
     * Devuelve `0` cuando el reporte no trae filas.
     */
    public function total(): int
    {
        return $this->first()?->totalAsInt() ?? 0;
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    public function count(): int
    {
        return \count($this->items);
    }

    /**
     * @return list<array{total: string|null, taxpayer_id: string|null}>
     */
    public function toArray(): array
    {
        return array_map(static fn (ReportTotalItem $item): array => $item->toArray(), $this->items);
    }

    public function isSuccess(): bool
    {
        return !$this->isError();
    }
}

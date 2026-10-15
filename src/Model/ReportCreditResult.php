<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Resultado de `report_credit`: los créditos añadidos al RFC en un periodo.
 *
 * El WSDL devuelve un arreglo `ReportTotalCredit` dentro de
 * `report_creditResult/result`. Cuando el rango no tiene movimientos, Finkok
 * responde `result` **vacío** en lugar de un error, así que
 * {@see isSuccess()} es `true` con una lista vacía.
 */
final class ReportCreditResult extends UtilitiesResult
{
    /**
     * @param list<ReportCreditItem> $items
     */
    public function __construct(
        private readonly array $items = [],
        ?string $error = null,
        ?string $rawResponse = null,
    ) {
        parent::__construct($error, $rawResponse);
    }

    /**
     * Movimientos de créditos, en el orden en que los devolvió Finkok.
     *
     * @return list<ReportCreditItem>
     */
    public function items(): array
    {
        return $this->items;
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
     * Suma de los créditos del reporte.
     *
     * Devuelve `0` cuando la lista está vacía y suma solo los movimientos con un
     * valor numérico interpretable.
     */
    public function totalCredits(): int
    {
        $total = 0;

        foreach ($this->items as $item) {
            $total += $item->creditAsInt() ?? 0;
        }

        return $total;
    }

    /**
     * @return list<array{credit: string|null, date: string|null, taxpayer_id: string|null}>
     */
    public function toArray(): array
    {
        return array_map(static fn (ReportCreditItem $item): array => $item->toArray(), $this->items);
    }

    public function isSuccess(): bool
    {
        return !$this->isError();
    }
}

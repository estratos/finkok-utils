<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Resultado de `report_credit`: los créditos añadidos a la cuenta del RFC.
 *
 * El WSDL devuelve un arreglo `ReportTotalCredit` dentro de
 * `report_creditResult/result`. Hay **dos** casos en los que la lista llega vacía y
 * significan cosas muy distintas:
 *
 *  - `result` **presente pero vacío** → la cuenta es de prepago y no tiene
 *    movimientos de créditos. {@see isUnlimited()} es `false`.
 *  - `result` **ausente del todo** → la cuenta es **ilimitada**, es decir, no
 *    consume créditos y por eso Finkok no lleva un reporte. {@see isUnlimited()}
 *    es `true`.
 *
 * Confundir ambos casos lleva a tratar una cuenta ilimitada como «sin créditos»,
 * que es justo lo contrario de lo que significa. La documentación de Finkok
 * distingue el caso en su propio ejemplo de consumo (`if response.result is None:
 * print("Usuario Ilimitado")`).
 */
final class ReportCreditResult extends UtilitiesResult
{
    /**
     * @param list<ReportCreditItem> $items
     * @param bool|null              $unlimited `true` si la cuenta es ilimitada,
     *                                          `false` si es de prepago, `null` si
     *                                          la respuesta no permite determinarlo
     */
    public function __construct(
        private readonly array $items = [],
        ?string $error = null,
        ?string $rawResponse = null,
        private readonly ?bool $unlimited = null,
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

    /**
     * `true` si la cuenta es **ilimitada** y por eso no hay reporte de créditos.
     *
     * `false` indica una cuenta de prepago (con o sin movimientos en el reporte).
     * `null` solo aparece cuando no hubo respuesta interpretable.
     */
    public function isUnlimited(): ?bool
    {
        return $this->unlimited;
    }

    /**
     * `true` si la cuenta es de prepago, es decir, sí consume créditos.
     */
    public function isPrepaid(): bool
    {
        return false === $this->unlimited;
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

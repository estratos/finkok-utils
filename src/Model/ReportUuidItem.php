<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Un comprobante del reporte `report_uuid`.
 *
 * Corresponde al tipo `s0:ReportUUID`, que el WSDL declara **completo**:
 * `uuid`, `taxpayer_id`, `rtaxpayer_id`, `date` y `total`. El arreglo vive en
 * `report_uuidResult/invoices/ReportUUID`.
 *
 * Advertencia sobre los fixtures: la documentación de Finkok y las respuestas
 * reales más comunes sólo incluyen `date` y `uuid`, de modo que el resto de los
 * campos se exponen como opcionales y `null`.
 */
final class ReportUuidItem
{
    public function __construct(
        /** Folio Fiscal del comprobante. */
        public readonly ?string $uuid = null,
        /** Fecha de emisión (`xs:dateTime`), tal cual la devuelve Finkok. */
        public readonly ?string $date = null,
        /** RFC del emisor. */
        public readonly ?string $taxpayerId = null,
        /** RFC del receptor. */
        public readonly ?string $receiverTaxpayerId = null,
        /** Total del comprobante (`xs:float`). */
        public readonly ?string $total = null,
    ) {
    }

    public function dateTime(\DateTimeZone|string|null $timeZone = null): ?\DateTimeImmutable
    {
        return ModelDate::parse($this->date, $timeZone);
    }

    public function totalAsFloat(): ?float
    {
        if (null === $this->total || !is_numeric(trim($this->total))) {
            return null;
        }

        return (float) trim($this->total);
    }

    /**
     * @return array{uuid: string|null, date: string|null, taxpayer_id: string|null, rtaxpayer_id: string|null, total: string|null}
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'date' => $this->date,
            'taxpayer_id' => $this->taxpayerId,
            'rtaxpayer_id' => $this->receiverTaxpayerId,
            'total' => $this->total,
        ];
    }
}

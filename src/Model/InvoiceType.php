<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

use Estratos\FinkokUtilsBundle\Exception\ValidationException;

/**
 * Tipo de comprobante que aceptan los reportes de utilerías.
 *
 * Corresponde al campo `invoice_type` del WSDL, tipado como `xs:string` libre. El
 * SAT codifica el tipo de comprobante con una letra en `cfdi:Comprobante/@TipoDeComprobante`:
 *
 * | Clave | Tipo de comprobante |
 * |-------|---------------------|
 * | `I`   | Ingreso             |
 * | `E`   | Egreso              |
 * | `T`   | Traslado            |
 * | `N`   | Nómina              |
 * | `P`   | Pago                |
 *
 * Enviar un valor fuera de este catálogo **no** produce un error en Finkok: los
 * reportes responden una lista vacía. Por eso el bundle valida localmente y falla
 * de inmediato.
 */
enum InvoiceType: string
{
    /** Ingreso (factura). Es el tipo del timbrado habitual. */
    case Ingreso = 'I';

    /** Egreso (nota de crédito). */
    case Egreso = 'E';

    /** Traslado (nota de remisión / traslado de mercancías). */
    case Traslado = 'T';

    /** Nómina. */
    case Nomina = 'N';

    /** Pago (complemento de recepción de pagos). */
    case Pago = 'P';

    /**
     * Interpreta la clave que envía la aplicación.
     *
     * Acepta la clave oficial (`I`, `E`, `T`, `N`, `P`) sin distinguir mayúsculas
     * de minúsculas, y también nombres legibles en español o inglés para comodidad
     * de quien consume la librería desde configuración.
     */
    public static function fromKey(string $value): self
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'i', 'ingreso', 'income', 'factura', 'invoice' => self::Ingreso,
            'e', 'egreso', 'expense', 'nota_credito', 'nota de credito', 'credit_note' => self::Egreso,
            't', 'traslado', 'transfer', 'remision', 'remisión' => self::Traslado,
            'n', 'nomina', 'nómina', 'payroll' => self::Nomina,
            'p', 'pago', 'payment', 'pagos' => self::Pago,
            default => throw ValidationException::forUnknownInvoiceType($value),
        };
    }

    /**
     * Describe el tipo en español, para mensajes y reportes.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Egreso => 'Egreso',
            self::Traslado => 'Traslado',
            self::Nomina => 'Nómina',
            self::Pago => 'Pago',
        };
    }
}

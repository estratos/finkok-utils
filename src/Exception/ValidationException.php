<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Exception;

/**
 * Los datos de entrada no son utilizables: RFC vacío, UUID con formato inválido,
 * rango de fechas invertido, mes fuera de 1..12, etc.
 *
 * Estas validaciones son **locales** (no llegan a Finkok). Existen porque el
 * servicio de utilerías responde listas vacías —en lugar de un error— cuando el
 * rango de fechas o el `invoice_type` son incorrectos, y ese silencio es mucho
 * más costoso de depurar que una excepción inmediata.
 */
final class ValidationException extends \InvalidArgumentException implements FinkokExceptionInterface
{
    public static function forEmptyArgument(string $argument): self
    {
        return new self(sprintf('El argumento "%s" es obligatorio y no puede estar vacío.', $argument));
    }

    public static function forInvalidUuid(string $uuid): self
    {
        return new self(sprintf(
            'El UUID "%s" no tiene el formato de un Folio Fiscal (8-4-4-4-12 caracteres hexadecimales).',
            $uuid,
        ));
    }

    public static function forInvalidRfc(string $rfc): self
    {
        return new self(sprintf(
            'El RFC "%s" no tiene un formato válido (12 o 13 caracteres, personas físicas y morales).',
            $rfc,
        ));
    }

    public static function forInvalidZipcode(string $zipcode): self
    {
        return new self(sprintf(
            'El código postal "%s" no es válido: el WSDL declara el patrón [0-9]{5}.',
            $zipcode,
        ));
    }

    public static function forInvalidPeriod(int $year, int $month): self
    {
        return new self(sprintf(
            'El periodo %04d-%02d no es válido: el mes debe estar entre 1 y 12 y el año entre 2000 y el actual.',
            $year,
            $month,
        ));
    }

    public static function forInvertedDateRange(string $from, string $to): self
    {
        return new self(sprintf(
            'El rango de fechas está invertido: date_from (%s) es posterior a date_to (%s). '
            .'Finkok respondería una lista vacía sin explicar el motivo.',
            $from,
            $to,
        ));
    }

    public static function forInvalidDate(string $value, string $argument): self
    {
        return new self(sprintf(
            'El valor "%s" del argumento "%s" no es una fecha válida. Se espera el formato AAAA-MM-DD o una fecha ISO 8601.',
            $value,
            $argument,
        ));
    }

    public static function forUnknownInvoiceType(string $type): self
    {
        return new self(sprintf(
            'El tipo de comprobante "%s" no es reconocido. Valores válidos: %s '
            .'(I ingreso, E egreso, T traslado, N nómina, P pago).',
            $type,
            '"'.implode('", "', ['I', 'E', 'T', 'N', 'P']).'"',
        ));
    }
}

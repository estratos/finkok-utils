<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Interpretación tolerante de las fechas que devuelve Finkok.
 *
 * Los reportes devuelven `xs:dateTime` sin zona horaria y con una precisión que
 * varía: `2019-01-13T14:15:16` en unos casos y `2019-01-13T14:15:16.171819` (seis
 * decimales de fracción de segundo) en otros. Un `new DateTimeImmutable()` directo
 * falla en PHP con más de seis decimales, así que aquí se trunca la fracción a
 * microsegundos antes de parsear.
 *
 * @internal
 */
final class ModelDate
{
    private function __construct()
    {
    }

    public static function parse(
        ?string $value,
        \DateTimeZone|string|null $timeZone = null,
    ): ?\DateTimeImmutable {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $value = self::truncateFraction(trim($value));

        try {
            return new \DateTimeImmutable($value, self::normalizeTimeZone($timeZone));
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Recorta la fracción de segundo a un máximo de 6 dígitos, que es lo que PHP
     * acepta.
     */
    private static function truncateFraction(string $value): string
    {
        $dot = strpos($value, '.');

        if (false === $dot) {
            return $value;
        }

        $fractionStart = $dot + 1;
        $length = \strlen($value);
        $end = $fractionStart;

        while ($end < $length && ctype_digit($value[$end])) {
            ++$end;
        }

        $digits = $end - $fractionStart;

        if ($digits <= 6) {
            return $value;
        }

        // Conserva el sufijo (por ejemplo `+00:00` o `Z`) que pueda seguir a la fracción.
        return substr($value, 0, $fractionStart + 6).substr($value, $end);
    }

    private static function normalizeTimeZone(\DateTimeZone|string|null $timeZone): ?\DateTimeZone
    {
        return match (true) {
            $timeZone instanceof \DateTimeZone => $timeZone,
            \is_string($timeZone) && '' !== trim($timeZone) => new \DateTimeZone($timeZone),
            default => null,
        };
    }
}

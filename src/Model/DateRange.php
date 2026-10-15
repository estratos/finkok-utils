<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

use Estratos\FinkokUtilsBundle\Exception\ValidationException;

/**
 * Rango de fechas para los reportes `report_total` y `report_uuid`.
 *
 * Existe porque el WSDL declara `date_from` y `date_to` como `xs:dateTime`
 * **obligatorios**, y Finkok no informa un error cuando el rango es incorrecto:
 * responde una lista vacía. Validar aquí convierte un reporte vacío e inexplicable
 * en una excepción accionable.
 *
 * Formato de serialización: `AAAA-MM-DDTHH:MM:SS`, sin zona horaria, que es el
 * formato que espera el PAC (la zona se asume la del centro de México).
 */
final class DateRange
{
    private function __construct(
        private readonly \DateTimeImmutable $from,
        private readonly \DateTimeImmutable $to,
        private readonly string $fromString,
        private readonly string $toString,
    ) {
    }

    /**
     * A partir de dos objetos de fecha.
     *
     * El extremo final se interpreta de forma inclusiva: si se pasa una fecha sin
     * hora (`2026-03-31`), `date_to` se envía como `2026-03-31T23:59:59` para que
     * el día completo quede incluido. Enviar `T00:00:00` —que es lo natural— deja
     * fuera todos los comprobantes emitidos ese día.
     */
    public static function between(\DateTimeInterface $from, \DateTimeInterface $to): self
    {
        $fromImmutable = \DateTimeImmutable::createFromInterface($from);
        $toImmutable = \DateTimeImmutable::createFromInterface($to);

        return self::fromPair($fromImmutable, $toImmutable);
    }

    /**
     * A partir de dos cadenas en formato `AAAA-MM-DD` o ISO 8601.
     */
    public static function fromStrings(string $from, string $to): self
    {
        $fromImmutable = self::parseString($from, 'date_from');
        $toImmutable = self::parseString($to, 'date_to');

        return self::fromPair($fromImmutable, $toImmutable);
    }

    /**
     * Rango de un solo día completo (`00:00:00` a `23:59:59`).
     */
    public static function forDay(\DateTimeInterface|string $day): self
    {
        $date = \is_string($day) ? self::parseString($day, 'day') : \DateTimeImmutable::createFromInterface($day);

        return self::fromPair($date->setTime(0, 0, 0), $date->setTime(23, 59, 59), endIsExplicitTime: true);
    }

    /**
     * Rango de un mes completo.
     *
     * @param int $year  año de 4 dígitos
     * @param int $month mes de 1 a 12
     */
    public static function forMonth(int $year, int $month): self
    {
        self::assertValidPeriod($year, $month);

        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));

        return self::fromPair($start, $start->modify('last day of this month')->setTime(23, 59, 59), endIsExplicitTime: true);
    }

    /**
     * Rango de un año completo.
     */
    public static function forYear(int $year): self
    {
        $start = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year));

        return self::fromPair($start, $start->setDate($year, 12, 31)->setTime(23, 59, 59), endIsExplicitTime: true);
    }

    /**
     * Rango entre dos periodos mensuales, ambos inclusive.
     *
     * Es la forma natural de expresar los reportes de Finkok: «los timbres de
     * 2026-01 a 2026-03». Internamente se convierte a fechas con hora, porque el
     * WSDL exige `xs:dateTime`.
     *
     * @param int $startYear  año inicial
     * @param int $startMonth mes inicial (1-12)
     * @param int $endYear    año final
     * @param int $endMonth   mes final (1-12)
     */
    public static function forPeriods(int $startYear, int $startMonth, int $endYear, int $endMonth): self
    {
        self::assertValidPeriod($startYear, $startMonth);
        self::assertValidPeriod($endYear, $endMonth);

        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $startYear, $startMonth));
        $end = (new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $endYear, $endMonth)))
            ->modify('last day of this month')
            ->setTime(23, 59, 59);

        return self::fromPair($start, $end, endIsExplicitTime: true);
    }

    public function from(): \DateTimeImmutable
    {
        return $this->from;
    }

    public function to(): \DateTimeImmutable
    {
        return $this->to;
    }

    /**
     * Valor literal para el argumento `date_from` del WSDL.
     */
    public function fromString(): string
    {
        return $this->fromString;
    }

    /**
     * Valor literal para el argumento `date_to` del WSDL.
     */
    public function toString(): string
    {
        return $this->toString;
    }

    /**
     * Días naturales que cubre el rango, contando ambos extremos.
     *
     * `forMonth(2026, 2)` corresponde a 28 días, no a 27: `DateInterval::$days`
     * cuenta los días **completos transcurridos** entre dos instantes, y como el
     * extremo final se extiende a las 23:59:59 el último día nunca se completa.
     */
    public function days(): int
    {
        $elapsed = $this->from->diff($this->to)->days ?? 0;

        return max(1, $elapsed + 1);
    }

    public function __toString(): string
    {
        return $this->fromString.' a '.$this->toString;
    }

    private static function fromPair(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        bool $endIsExplicitTime = false,
    ): self {
        // Si el extremo final no trae hora explícita (medianoche), se extiende al
        // final del día para que el día completo quede incluido.
        if (!$endIsExplicitTime && '00:00:00' === $to->format('H:i:s')) {
            $to = $to->setTime(23, 59, 59);
        }

        $fromString = $from->format('Y-m-d\TH:i:s');
        $toString = $to->format('Y-m-d\TH:i:s');

        if ($fromString > $toString) {
            throw ValidationException::forInvertedDateRange($fromString, $toString);
        }

        return new self($from, $to, $fromString, $toString);
    }

    private static function parseString(string $value, string $argument): \DateTimeImmutable
    {
        $value = trim($value);

        if ('' === $value) {
            throw ValidationException::forEmptyArgument($argument);
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw ValidationException::forInvalidDate($value, $argument);
        }
    }

    private static function assertValidPeriod(int $year, int $month): void
    {
        $currentYear = (int) (new \DateTimeImmutable('today'))->format('Y');

        if ($month < 1 || $month > 12 || $year < 2000 || $year > $currentYear) {
            throw ValidationException::forInvalidPeriod($year, $month);
        }
    }
}

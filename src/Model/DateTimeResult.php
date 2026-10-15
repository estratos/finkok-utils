<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Resultado de `datetime`: la fecha y hora de los servidores de Finkok.
 *
 * Se usa para fechar comprobantes con la hora oficial del PAC y evitar el rechazo
 * del SAT por desfase de reloj (`CFDI40147`), que ocurre cuando el `Fecha` del
 * CFDI supera los 30 minutos de diferencia con la hora de recepción declarada.
 *
 * El campo viaja como `xs:string` sin zona horaria (por ejemplo
 * `2019-01-13T14:15:16`); se expone también como `DateTimeImmutable` interpretado
 * en la zona horaria que indique el consumidor.
 */
final class DateTimeResult extends UtilitiesResult
{
    public function __construct(
        private readonly ?string $datetime = null,
        ?string $error = null,
        ?string $rawResponse = null,
    ) {
        parent::__construct($error, $rawResponse);
    }

    /**
     * Fecha y hora del servidor en formato `AAAA-MM-DDTHH:MM:SS`, o `null`.
     */
    public function datetime(): ?string
    {
        return $this->datetime;
    }

    /**
     * Fecha y hora como objeto, o `null` si Finkok no devolvió un valor
     * interpretable.
     *
     * @param \DateTimeZone|string|null $timeZone zona horaria (por omisión, la del proceso)
     */
    public function toDateTime(\DateTimeZone|string|null $timeZone = null): ?\DateTimeImmutable
    {
        if (null === $this->datetime || '' === trim($this->datetime)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($this->datetime, self::normalizeTimeZone($timeZone));
        } catch (\Exception) {
            return null;
        }
    }

    public function hasDatetime(): bool
    {
        return null !== $this->datetime && '' !== trim($this->datetime);
    }

    public function isSuccess(): bool
    {
        return !$this->isError() && $this->hasDatetime();
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

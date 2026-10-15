<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Resultado de `get_moneda_usd`: el tipo de cambio del dólar que aplica Finkok.
 *
 * El WSDL declara este tipo como `s0:MonedaResult` con el campo `moneda`. Se
 * incluye porque pertenece al mismo WSDL de utilerías, aunque no formara parte de
 * la lista de métodos solicitada.
 */
final class MonedaResult extends UtilitiesResult
{
    public function __construct(
        private readonly ?string $moneda = null,
        ?string $error = null,
        ?string $rawResponse = null,
    ) {
        parent::__construct($error, $rawResponse);
    }

    /**
     * Tipo de cambio como texto, tal cual lo devuelve Finkok (por ejemplo `17.1234`).
     */
    public function moneda(): ?string
    {
        return $this->moneda;
    }

    /**
     * Tipo de cambio como número, o `null` si no es interpretable.
     */
    public function rate(): ?float
    {
        if (null === $this->moneda || !is_numeric(trim($this->moneda))) {
            return null;
        }

        return (float) trim($this->moneda);
    }

    public function hasMoneda(): bool
    {
        return null !== $this->moneda && '' !== trim($this->moneda);
    }

    public function isSuccess(): bool
    {
        return !$this->isError() && $this->hasMoneda();
    }
}

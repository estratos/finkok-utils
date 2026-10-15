<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Resultado de `get_idccp`: el identificador del complemento Carta Porte.
 *
 * El `idCCP` es el folio que el SAT asigna al complemento Carta Porte
 * (`ccp:IdCCP`), con formato `CCP` + UUID. Finkok lo devuelve como `xs:string`
 * dentro del nodo `idccp`.
 */
final class IdCcpResult extends UtilitiesResult
{
    public function __construct(
        private readonly ?string $idccp = null,
        ?string $error = null,
        ?string $rawResponse = null,
    ) {
        parent::__construct($error, $rawResponse);
    }

    /**
     * Identificador del complemento Carta Porte, o `null`.
     */
    public function idccp(): ?string
    {
        return $this->idccp;
    }

    public function hasIdccp(): bool
    {
        return null !== $this->idccp && '' !== trim($this->idccp);
    }

    public function isSuccess(): bool
    {
        return !$this->isError() && $this->hasIdccp();
    }
}

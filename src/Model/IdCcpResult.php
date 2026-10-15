<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Resultado de `get_idccp`: el identificador del complemento Carta Porte.
 *
 * Finkok expide el `idCCP` y lo devuelve como `xs:string` dentro del nodo `idccp`.
 * El valor mide 36 caracteres y empieza con `CCC`; consulta {@see IdCcp} para el
 * formato completo, la validación y la generación.
 *
 * Ejemplo de respuesta oficial:
 *
 * ```xml
 * <tns:get_idccpResponse xmlns:tns="http://facturacion.finkok.com/utilities">
 *   <tns:get_idccpResult>
 *     <s0:idccp>CCC47D35-5855-4E23-8A52-3AB33221AE09</s0:idccp>
 *   </tns:get_idccpResult>
 * </tns:get_idccpResponse>
 * ```
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

    /**
     * `true` si el valor devuelto cumple el formato del `IdCCP`.
     *
     * Un valor presente pero mal formado indica que Finkok cambió el formato o que
     * un proxy alteró la respuesta; conviene tratarlo como no utilizable antes de
     * escribirlo en el CFDI, porque el SAT rechazaría el comprobante.
     */
    public function isValid(): bool
    {
        return IdCcp::isValid($this->idccp);
    }

    /**
     * Identificador en su forma canónica en mayúsculas, o `null` si no cumple el
     * formato.
     */
    public function normalized(): ?string
    {
        return $this->isValid() ? strtoupper((string) $this->idccp) : null;
    }

    public function isSuccess(): bool
    {
        return !$this->isError() && $this->hasIdccp();
    }
}

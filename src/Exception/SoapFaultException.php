<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Exception;

/**
 * El servicio respondió un SOAP Fault (`soap:Fault` / `soapenv:Fault`) a nivel de
 * protocolo, es decir, la petición no llegó a ejecutarse como operación de
 * negocio.
 *
 * No debe confundirse con los errores de negocio de Finkok (credenciales
 * inválidas, RFC sin créditos, …), los cuales viajan en el nodo `error` de una
 * respuesta correcta y se exponen mediante los DTO de resultado.
 */
final class SoapFaultException extends FinkokException
{
    public function __construct(
        string $message,
        private readonly ?string $faultCode = null,
        private readonly ?string $faultString = null,
        private readonly ?string $endpoint = null,
        private readonly ?string $operation = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromFault(
        string $endpoint,
        string $operation,
        ?string $faultCode,
        ?string $faultString,
    ): self {
        return new self(
            sprintf(
                'SOAP Fault al invocar %s en %s [%s]: %s',
                $operation,
                $endpoint,
                $faultCode ?? 'sin faultcode',
                $faultString ?? 'sin faultstring',
            ),
            $faultCode,
            $faultString,
            $endpoint,
            $operation,
        );
    }

    public function getFaultCode(): ?string
    {
        return $this->faultCode;
    }

    public function getFaultString(): ?string
    {
        return $this->faultString;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function getOperation(): ?string
    {
        return $this->operation;
    }
}

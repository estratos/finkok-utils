<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap;

/**
 * Transporte SOAP desacoplado del cliente HTTP.
 *
 * El bundle incluye {@see HttpClientSoapTransport}, construido sobre Symfony
 * HttpClient, de modo que **no requiere `ext-soap`**. Implementar esta interfaz
 * permite sustituir el transporte en pruebas o inyectar un cliente con
 * interceptores propios.
 */
interface SoapTransportInterface
{
    /**
     * Envía la petición y devuelve la respuesta SOAP.
     *
     * @throws \Estratos\FinkokUtilsBundle\Exception\TransportException     fallo de red o HTTP
     * @throws \Estratos\FinkokUtilsBundle\Exception\SoapFaultException     SOAP Fault de protocolo
     * @throws \Estratos\FinkokUtilsBundle\Exception\UnexpectedResponseException respuesta no interpretable
     */
    public function send(SoapRequest $request): SoapResponse;
}

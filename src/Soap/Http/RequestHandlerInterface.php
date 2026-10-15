<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Http;

/**
 * Ejecutor del POST HTTP que hay debajo del transporte SOAP.
 *
 * Existe para que el envelope se construya y se interprete **una sola vez**, en
 * {@see \Estratos\FinkokUtilsBundle\Soap\HttpClientSoapTransport}, mientras el envío
 * concreto se delega a la implementación que prefiera la aplicación:
 *
 *  - {@see SymfonyRequestHandler} sobre Symfony HttpClient;
 *  - {@see \Estratos\FinkokUtilsBundle\Soap\Psr18\Psr18RequestHandler} sobre un
 *    cliente PSR-18.
 *
 * El bundle **no usa `ext-soap`**: el envelope se construye a mano a partir del
 * WSDL real y se envía como un POST con `Content-Type: text/xml` y la cabecera
 * `SOAPAction`, que es exactamente lo que haría un cliente generado desde el WSDL.
 */
interface RequestHandlerInterface
{
    /**
     * Envía el envelope y devuelve la respuesta cruda.
     *
     * No debe lanzar por códigos de estado HTTP: el transporte necesita inspeccionar
     * el cuerpo, porque un SOAP Fault puede llegar con HTTP 500. Solo las fallas de
     * conexión se propagan, y el transporte las envuelve en
     * {@see \Estratos\FinkokUtilsBundle\Exception\TransportException}.
     *
     * @param array<string, string> $headers cabeceras SOAP (Content-Type, SOAPAction, …)
     *
     * @throws \Estratos\FinkokUtilsBundle\Exception\TransportException si no hay conexión
     */
    public function post(string $url, string $body, array $headers, float $timeout): HttpResponse;
}

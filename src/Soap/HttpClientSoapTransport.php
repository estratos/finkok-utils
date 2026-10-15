<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap;

use Estratos\FinkokUtilsBundle\Exception\TransportException as FinkokTransportException;
use Estratos\FinkokUtilsBundle\Exception\UnexpectedResponseException;
use Estratos\FinkokUtilsBundle\Soap\Http\RequestHandlerInterface;
use Estratos\FinkokUtilsBundle\Soap\Http\SymfonyRequestHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transporte SOAP independiente de `ext-soap`.
 *
 * No usa la extensión SOAP de PHP: construye el envelope XML a mano a partir del
 * WSDL real (`utilities.wsdl`), lo envía por POST con `Content-Type: text/xml` y la
 * cabecera `SOAPAction`, y parsea la respuesta. Eso permite:
 *
 *  - funcionar en instalaciones donde `ext-soap` no está disponible;
 *  - probar el bundle con un cliente simulado e inspeccionar el XML exacto que se
 *    envía a Finkok;
 *  - enviar con Symfony HttpClient **o** con cualquier cliente PSR-18 sin duplicar
 *    la lógica del envelope, gracias a {@see RequestHandlerInterface}.
 */
final class HttpClientSoapTransport implements SoapTransportInterface
{
    public const DEFAULT_TIMEOUT = 30.0;

    public const DEFAULT_USER_AGENT = 'finkok-utils/1.0';

    private readonly LoggerInterface $logger;

    private readonly RequestHandlerInterface $handler;

    /**
     * @param RequestHandlerInterface|HttpClientInterface $handler cómo se envía el POST;
     *                                                                   se acepta el cliente
     *                                                                   de Symfony directamente
     *                                                                   por comodidad, y se
     *                                                                   envuelve en
     *                                                                   {@see SymfonyRequestHandler}
     */
    public function __construct(
        RequestHandlerInterface|HttpClientInterface $handler,
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
        ?LoggerInterface $logger = null,
        /**
         * Cuando es `true`, el envelope completo se escribe en el log. Debe
         * permanecer desactivado en producción porque el XML contiene datos
         * fiscales (RFC, montos, UUID y, en `get_xml`, el CFDI completo).
         */
        private readonly bool $logPayloads = false,
        private readonly string $userAgent = self::DEFAULT_USER_AGENT,
    ) {
        $this->handler = $handler instanceof RequestHandlerInterface
            ? $handler
            : new SymfonyRequestHandler($handler);
        $this->logger = $logger ?? new NullLogger();
    }

    public function send(SoapRequest $request): SoapResponse
    {
        $payload = $request->toXml();

        $this->logger->debug('Finkok utilerías: enviando petición SOAP', [
            'operacion' => $request->operation,
            'endpoint' => $request->endpoint,
            'argumentos' => $request->debugArguments(),
            'payload' => $this->logPayloads ? $payload : sprintf('<%d bytes omitidos>', \strlen($payload)),
        ]);

        $startedAt = microtime(true);

        try {
            $httpResponse = $this->handler->post(
                $request->endpoint,
                $payload,
                $this->headersFor($request),
                $request->timeout ?? $this->timeout,
            );
        } catch (FinkokTransportException $exception) {
            $this->logger->error('Finkok utilerías: fallo de conexión', [
                'operacion' => $request->operation,
                'endpoint' => $request->endpoint,
                'excepcion' => $exception->getMessage(),
            ]);

            throw FinkokTransportException::forConnectionFailure(
                $request->endpoint,
                $request->operation,
                $exception,
            );
        }

        $elapsed = round((microtime(true) - $startedAt) * 1000, 2);
        $soapResponse = new SoapResponse(
            $httpResponse->body,
            $httpResponse->statusCode,
            $httpResponse->headers,
        );

        $this->logger->debug('Finkok utilerías: respuesta recibida', [
            'operacion' => $request->operation,
            'http' => $httpResponse->statusCode,
            'ms' => $elapsed,
            'fault' => $soapResponse->fault()?->describe(),
        ]);

        // Un SOAP Fault trae más información que el código HTTP, así que se
        // evalúa primero incluso cuando el HTTP es 500.
        $soapResponse->assertNoFault($request->endpoint, $request->operation);

        if ($httpResponse->statusCode >= 400) {
            throw FinkokTransportException::forHttpError(
                $request->endpoint,
                $request->operation,
                $httpResponse->statusCode,
                $httpResponse->body,
            );
        }

        if (!$soapResponse->isSoapEnvelope()) {
            throw new UnexpectedResponseException(
                sprintf(
                    'Finkok respondió HTTP %d con un cuerpo que no es un envelope SOAP%s al invocar %s en %s. '
                    .'Verifica que la URL corresponda al Web Service de utilerías '
                    .'(/servicios/soap/utilities) y que no haya un proxy intermediando.',
                    $httpResponse->statusCode,
                    $soapResponse->isXml() ? ' válido' : ' ni XML válido',
                    $request->operation,
                    $request->endpoint,
                ),
                $request->endpoint,
                $request->operation,
                $httpResponse->body,
            );
        }

        return $soapResponse;
    }

    /**
     * Cabeceras SOAP 1.1 que espera Finkok.
     *
     * @return array<string, string>
     */
    private function headersFor(SoapRequest $request): array
    {
        return [
            'Content-Type' => 'text/xml; charset=utf-8',
            'SOAPAction' => '"'.$request->soapAction().'"',
            'Accept' => 'text/xml, application/soap+xml, */*',
            'User-Agent' => $this->userAgent,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Fakes;

use Estratos\FinkokUtilsBundle\Soap\SoapRequest;
use Estratos\FinkokUtilsBundle\Soap\SoapResponse;
use Estratos\FinkokUtilsBundle\Soap\SoapTransportInterface;

/**
 * Transporte de prueba que delega en un callable.
 *
 * Permite simular respuestas distintas por intento —necesario para probar que
 * `get_xml` reintenta cuando Finkok devuelve una ruta en lugar del CFDI— sin tener
 * que encolar todos los cuerpos por adelantado.
 */
final class CallableSoapTransport implements SoapTransportInterface
{
    /** @var list<SoapRequest> */
    public array $requests = [];

    /** @param callable(SoapRequest, int): SoapResponse $handler */
    public function __construct(private readonly mixed $handler)
    {
    }

    public function send(SoapRequest $request): SoapResponse
    {
        $this->requests[] = $request;

        $handler = $this->handler;

        return $handler($request, \count($this->requests) - 1);
    }

    public function callCount(): int
    {
        return \count($this->requests);
    }
}

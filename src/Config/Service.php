<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Config;

/**
 * Web Services de Finkok soportados por este bundle.
 *
 * A diferencia del bundle de timbrado, este adaptador consume un único WSDL
 * (`utilities.wsdl`). El enum se conserva para que el resolver de endpoints y los
 * perfiles de credenciales compartan la misma forma que el resto de los bundles de
 * Finkok y para poder añadir servicios de utilerías adicionales sin romper la API.
 */
enum Service: string
{
    /** Utilerías: fecha/hora, descarga de XML, reportes de créditos, totales y UUID. */
    case Utilities = 'utilities';

    /**
     * `targetNamespace` SOAP del servicio, requerido para construir el envelope
     * (`utilities.wsdl` es document/literal con `elementFormDefault="qualified"`).
     */
    public function namespace(): string
    {
        return match ($this) {
            self::Utilities => 'http://facturacion.finkok.com/utilities',
        };
    }

    /**
     * Ruta del WSDL dentro del host de Finkok (sin la URL base).
     */
    public function wsdlPath(): string
    {
        return match ($this) {
            self::Utilities => '/servicios/soap/utilities.wsdl',
        };
    }

    /**
     * Host de Finkok para el ambiente indicado.
     */
    public static function host(Environment $environment): string
    {
        return $environment->isProduction()
            ? 'https://facturacion.finkok.com'
            : 'https://demo-facturacion.finkok.com';
    }

    /**
     * URL del endpoint SOAP (sin extensión) para el ambiente indicado.
     *
     * Es la URL del `soap:address` que declara el WSDL: la operación se invoca
     * contra `/servicios/soap/utilities`, no contra `/utilities.wsdl`.
     */
    public function defaultEndpoint(Environment $environment): string
    {
        return self::host($environment).'/servicios/soap/'.$this->value;
    }

    /**
     * URL pública del WSDL, útil para depuración o generación de clientes.
     */
    public function wsdlUrl(Environment $environment): string
    {
        return self::host($environment).$this->wsdlPath();
    }
}

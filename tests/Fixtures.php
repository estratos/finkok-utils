<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests;

/**
 * Carga las respuestas SOAP de prueba desde `tests/Fixtures/responses`.
 */
final class Fixtures
{
    private function __construct()
    {
    }

    public static function path(string $name): string
    {
        $path = __DIR__.'/Fixtures/responses/'.$name;

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('No existe el fixture "%s" (%s).', $name, $path));
        }

        return $path;
    }

    /**
     * Contenido crudo del fixture, útil para cuerpos no XML (por ejemplo HTML).
     */
    public static function content(string $name): string
    {
        $content = file_get_contents(self::path($name));

        if (false === $content) {
            throw new \RuntimeException(sprintf('No fue posible leer el fixture "%s".', $name));
        }

        return $content;
    }

    /**
     * Elemento de resultado de un fixture, listo para pasarlo a un hidratador.
     *
     * @param string $operation operación del WSDL (por ejemplo `report_uuid`)
     */
    public static function resultElement(string $name, string $operation): ?\DOMElement
    {
        $response = new \Estratos\FinkokUtilsBundle\Soap\SoapResponse(self::content($name));

        return $response->result($operation);
    }
}

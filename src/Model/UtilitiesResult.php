<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Base de los resultados del Web Service de utilerías.
 *
 * Todas las operaciones comparten la misma forma: un nodo de resultado con datos
 * y un nodo opcional `error` con un mensaje de negocio («El usuario o contraseña
 * son inválidos», «No se encontró el XML», …). A diferencia de los servicios de
 * timbrado, utilerías **no** usa el arreglo `<Incidencias>`: los errores llegan en
 * texto plano dentro de `error`.
 *
 * La respuesta cruda se conserva siempre para auditoría y para depurar
 * discrepancias entre lo que documenta el WSDL y lo que el servidor devuelve.
 */
abstract class UtilitiesResult
{
    public function __construct(
        protected readonly ?string $error = null,
        protected readonly ?string $rawResponse = null,
    ) {
    }

    /**
     * Mensaje de error de negocio reportado por Finkok, o `null` si no hubo.
     *
     * Un valor no nulo no significa necesariamente que la operación falló: algunos
     * métodos devuelven un texto informativo junto con datos válidos. Usa
     * {@see isError()} para decidir si hay que descartar el resultado.
     */
    public function error(): ?string
    {
        return $this->error;
    }

    public function isError(): bool
    {
        return null !== $this->error && '' !== trim($this->error);
    }

    /**
     * Respuesta SOAP cruda, tal como la devolvió Finkok.
     */
    public function rawResponse(): ?string
    {
        return $this->rawResponse;
    }

    /**
     * `true` cuando la operación no reportó error de negocio.
     */
    abstract public function isSuccess(): bool;
}

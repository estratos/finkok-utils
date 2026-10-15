<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Config;

/**
 * Resuelve los perfiles de credenciales configurados.
 *
 * El bundle expone el proveedor como servicio (`finkok_utils.credentials_provider`)
 * para que la aplicación obtenga el perfil que necesita y lo pase explícitamente
 * a cada llamada:
 *
 * ```php
 * $perfil = $provider->get('sucursal_norte');
 * $utils->reportUuid('AAA010101AAA', $desde, $hasta, credentials: $perfil);
 * ```
 *
 * El paso explícito —en lugar de un «perfil activo» global— mantiene el bundle
 * seguro en workers de larga vida (Messenger, RoadRunner, Swoole), donde el estado
 * global provoca que un reporte se consulte con el RFC equivocado.
 */
interface CredentialsProviderInterface
{
    /**
     * Devuelve el perfil indicado, o el perfil por defecto si `$name` es `null`.
     *
     * @throws \Estratos\FinkokUtilsBundle\Exception\ProfileNotFoundException
     */
    public function get(?string $name = null): CredentialsInterface;

    /**
     * `true` si existe un perfil con ese nombre.
     */
    public function has(string $name): bool;

    /**
     * Perfil por defecto.
     *
     * @throws \Estratos\FinkokUtilsBundle\Exception\ProfileNotFoundException
     */
    public function default(): CredentialsInterface;

    /**
     * Nombres de todos los perfiles configurados.
     *
     * @return list<string>
     */
    public function names(): array;
}

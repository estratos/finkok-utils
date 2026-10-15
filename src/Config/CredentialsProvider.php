<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Config;

use Estratos\FinkokUtilsBundle\Exception\ProfileNotFoundException;

/**
 * Proveedor de credenciales en memoria a partir de un mapa de perfiles.
 *
 * Es la implementación que el bundle ofrece «de fábrica», útil para aplicaciones
 * que leen los secretos de variables de entorno. Las aplicaciones multi-tenant
 * pueden implementar {@see CredentialsProviderInterface} para resolver el perfil
 * desde la base de datos o desde el inquilino activo.
 *
 * El perfil por defecto es el primero registrado, salvo que se indique otro
 * explícitamente; así `$provider->get()` funciona sin conocer los nombres.
 */
final class CredentialsProvider implements CredentialsProviderInterface
{
    /** @var array<string, CredentialsInterface> */
    private readonly array $profiles;

    private readonly ?string $defaultName;

    /**
     * @param iterable<string, CredentialsInterface> $profiles
     */
    public function __construct(iterable $profiles = [], ?string $defaultName = null)
    {
        $indexed = [];

        foreach ($profiles as $name => $profile) {
            $key = \is_string($name) ? $name : $profile->name();
            $indexed[$key] = $profile;
        }

        if (null !== $defaultName && !isset($indexed[$defaultName])) {
            throw ProfileNotFoundException::forName($defaultName, array_keys($indexed));
        }

        $this->profiles = $indexed;
        $this->defaultName = $defaultName ?? (array_key_first($indexed) ?: null);
    }

    public function get(?string $name = null): CredentialsInterface
    {
        if (null === $name) {
            return $this->default();
        }

        if (!isset($this->profiles[$name])) {
            throw ProfileNotFoundException::forName($name, $this->names());
        }

        return $this->profiles[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->profiles[$name]);
    }

    public function default(): CredentialsInterface
    {
        if (null === $this->defaultName) {
            throw new ProfileNotFoundException(
                'No hay perfiles de credenciales de Finkok configurados. Construye el proveedor con al menos '
                .'un perfil, por ejemplo: new CredentialsProvider([new Credentials("matriz", $usuario, $contrasena)]).',
            );
        }

        return $this->profiles[$this->defaultName];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->profiles);
    }
}

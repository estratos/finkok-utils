<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Config;

use Estratos\FinkokUtilsBundle\Exception\ConfigurationException;

/**
 * Implementación inmutable de un perfil de credenciales.
 *
 * Es un objeto de valores: no lee archivos ni variables de entorno y por lo tanto
 * es seguro de compartir entre peticiones. Quien construye el perfil (la
 * aplicación, un proveedor que lee la base de datos o el inquilino activo) es
 * responsable de resolver los secretos.
 */
final class Credentials implements CredentialsInterface
{
    /**
     * @param array<string, array<string, string>> $endpoints sobrescritura de
     *                                                         endpoints: servicio => ambiente => URL
     */
    public function __construct(
        private readonly string $name,
        private readonly string $username,
        private readonly string $password,
        private readonly ?string $taxpayerId = null,
        private readonly Environment $environment = Environment::Demo,
        private readonly array $endpoints = [],
    ) {
        if ('' === trim($name)) {
            throw new ConfigurationException('El perfil de credenciales debe tener un nombre.');
        }

        if ('' === trim($username)) {
            throw new ConfigurationException(sprintf('El perfil "%s" no define el usuario de Finkok.', $name));
        }

        if ('' === trim($password)) {
            throw new ConfigurationException(sprintf('El perfil "%s" no define la contraseña de Finkok.', $name));
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function taxpayerId(): ?string
    {
        if (null === $this->taxpayerId || '' === trim($this->taxpayerId)) {
            return null;
        }

        return strtoupper(trim($this->taxpayerId));
    }

    public function requireTaxpayerId(): string
    {
        $taxpayerId = $this->taxpayerId();

        if (null === $taxpayerId) {
            throw new ConfigurationException(sprintf(
                'El perfil "%s" no define "taxpayerId" (RFC del emisor), requerido para los reportes '
                .'de créditos, totales y UUID. Agrégalo al construir el perfil o pásalo explícitamente '
                .'en la llamada.',
                $this->name,
            ));
        }

        return $taxpayerId;
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    public function endpoint(Service $service, Environment $environment): ?string
    {
        $url = $this->endpoints[$service->value][$environment->value] ?? null;

        return null !== $url && '' !== trim($url) ? $url : null;
    }

    public function withEnvironment(Environment $environment): static
    {
        if ($environment === $this->environment) {
            return $this;
        }

        return new self(
            $this->name,
            $this->username,
            $this->password,
            $this->taxpayerId,
            $environment,
            $this->endpoints,
        );
    }

    /**
     * Nunca expone la contraseña al volcar el objeto (por ejemplo en un `dump()`
     * o en el profiler de Symfony).
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'password' => '***',
            'taxpayerId' => $this->taxpayerId(),
            'environment' => $this->environment->value,
            'endpoints' => array_keys($this->endpoints),
        ];
    }
}

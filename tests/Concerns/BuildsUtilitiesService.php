<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Concerns;

use Estratos\FinkokUtilsBundle\Config\Credentials;
use Estratos\FinkokUtilsBundle\Config\CredentialsProvider;
use Estratos\FinkokUtilsBundle\Config\EndpointResolver;
use Estratos\FinkokUtilsBundle\Config\Environment;
use Estratos\FinkokUtilsBundle\Soap\SoapTransportInterface;

/**
 * Atajos para construir el servicio de utilerías en las pruebas.
 */
trait BuildsUtilitiesService
{
    protected function credentials(
        string $username = 'usuario.demo',
        string $password = 'secreto.demo',
        ?string $taxpayerId = 'EKU9003173C9',
        Environment $environment = Environment::Demo,
    ): Credentials {
        return new Credentials(
            name: 'pruebas',
            username: $username,
            password: $password,
            taxpayerId: $taxpayerId,
            environment: $environment,
        );
    }

    protected function provider(?Credentials $credentials = null): CredentialsProvider
    {
        return new CredentialsProvider([$credentials ?? $this->credentials()]);
    }

    /**
     * Servicio listo para operar contra el transporte indicado.
     */
    protected function service(
        SoapTransportInterface $transport,
        ?Credentials $credentials = null,
        int $xmlMaxAttempts = 3,
    ): \Estratos\FinkokUtilsBundle\Service\UtilitiesService {
        return new \Estratos\FinkokUtilsBundle\Service\UtilitiesService(
            transport: $transport,
            endpointResolver: new EndpointResolver(),
            credentialsProvider: $this->provider($credentials),
            xmlMaxAttempts: $xmlMaxAttempts,
        );
    }
}

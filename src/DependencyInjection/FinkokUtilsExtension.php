<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\DependencyInjection;

use Estratos\FinkokUtilsBundle\Config\EndpointResolver;
use Estratos\FinkokUtilsBundle\Contract\UtilitiesServiceInterface;
use Estratos\FinkokUtilsBundle\DependencyInjection\Compiler\WiringPass;
use Estratos\FinkokUtilsBundle\Http\HttpClientFactory;
use Estratos\FinkokUtilsBundle\Service\UtilitiesService;
use Estratos\FinkokUtilsBundle\Soap\HttpClientSoapTransport;
use Estratos\FinkokUtilsBundle\Soap\SoapTransportInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Registra los servicios del bundle a partir de la configuración `finkok_utils`.
 */
final class FinkokUtilsExtension extends Extension
{
    public function getAlias(): string
    {
        return 'finkok_utils';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{
         *     endpoints: array<string, array<string, string>>,
         *     http: array{timeout: float, log_payloads: bool, user_agent: string, retry: array<string, mixed>},
         *     xml_max_attempts: int
         * } $config
         */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerEndpointResolver($container, $config['endpoints']);
        $this->registerHttp($container, $config['http']);
        $this->registerTransport($container, $config['http']);
        $this->registerServices($container, $config['xml_max_attempts']);
    }

    /**
     * @param array<string, array<string, string>> $endpoints
     */
    private function registerEndpointResolver(ContainerBuilder $container, array $endpoints): void
    {
        $container->register('finkok_utils.endpoint_resolver', EndpointResolver::class)
            ->setArguments([$endpoints])
            ->setPublic(false);
    }

    /**
     * @param array{timeout: float, log_payloads: bool, user_agent: string, retry: array<string, mixed>} $http
     */
    private function registerHttp(ContainerBuilder $container, array $http): void
    {
        $container->register(WiringPass::FACTORY_SERVICE, HttpClientFactory::class)
            ->setArguments([
                // El cliente base lo inyecta WiringPass si la aplicación tiene el
                // servicio `http_client`; mientras tanto la fábrica crea uno.
                '$base' => null,
                '$options' => ['timeout' => $http['timeout']],
                '$retryEnabled' => (bool) ($http['retry']['enabled'] ?? true),
                '$maxRetries' => (int) ($http['retry']['max_retries'] ?? 2),
                '$retry' => $http['retry'],
            ])
            ->setPublic(false);

        $container->register('finkok_utils.http_client', HttpClientInterface::class)
            ->setFactory([new Reference(WiringPass::FACTORY_SERVICE), 'create'])
            ->setPublic(false);
    }

    /**
     * @param array{timeout: float, log_payloads: bool, user_agent: string, retry: array<string, mixed>} $http
     */
    private function registerTransport(ContainerBuilder $container, array $http): void
    {
        $container->register('finkok_utils.transport', HttpClientSoapTransport::class)
            ->setArguments([
                new Reference('finkok_utils.http_client'),
                $http['timeout'],
                // `finkok_utils.logger` siempre existe: WiringPass lo apunta al
                // logger de la aplicación o a un NullLogger.
                new Reference(WiringPass::LOGGER_SERVICE),
                $http['log_payloads'],
                $http['user_agent'],
            ])
            ->setPublic(false);

        $container->setAlias(SoapTransportInterface::class, 'finkok_utils.transport')
            ->setPublic(true);
    }

    private function registerServices(ContainerBuilder $container, int $xmlMaxAttempts): void
    {
        $container->register('finkok_utils.utilities_service', UtilitiesService::class)
            ->setArguments([
                new Reference('finkok_utils.transport'),
                new Reference('finkok_utils.endpoint_resolver'),
                new Reference(WiringPass::CREDENTIALS_SERVICE),
                new Reference(WiringPass::LOGGER_SERVICE),
                $xmlMaxAttempts,
            ])
            ->setPublic(true);

        $container->setAlias(UtilitiesServiceInterface::class, 'finkok_utils.utilities_service')
            ->setPublic(true);
    }
}

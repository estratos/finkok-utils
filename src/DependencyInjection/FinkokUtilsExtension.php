<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\DependencyInjection;

use Estratos\FinkokUtilsBundle\Config\EndpointResolver;
use Estratos\FinkokUtilsBundle\Contract\UtilitiesServiceInterface;
use Estratos\FinkokUtilsBundle\DependencyInjection\Compiler\WiringPass;
use Estratos\FinkokUtilsBundle\Http\HttpClientFactory;
use Estratos\FinkokUtilsBundle\Service\UtilitiesService;
use Estratos\FinkokUtilsBundle\Soap\HttpClientSoapTransport;
use Estratos\FinkokUtilsBundle\Soap\Http\SymfonyRequestHandler;
use Estratos\FinkokUtilsBundle\Soap\Psr18\Psr18RequestHandler;
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
         *     xml_max_attempts: int,
         *     psr18: array{enabled: bool, client: string|null, request_factory: string|null}
         * } $config
         */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerEndpointResolver($container, $config['endpoints']);
        $this->registerHttp($container, $config['http']);
        $this->registerTransport($container, $config['http']);
        $this->registerServices($container, $config['xml_max_attempts']);

        // Se guardan para que WiringPass decida el transporte en tiempo de
        // compilación, cuando ya se conocen los servicios de la aplicación.
        $container->setParameter('finkok_utils.psr18', $config['psr18']);
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
        // Ejecutor sobre Symfony HttpClient (el que se usa por omisión). Tiene
        // timeout garantizado y es el único con política de reintentos.
        $container->register('finkok_utils.symfony_request_handler', SymfonyRequestHandler::class)
            ->setArguments([new Reference('finkok_utils.http_client')])
            ->setPublic(false);

        // Ejecutor sobre un cliente PSR-18. Solo se cablea si la aplicación registra
        // el cliente **y** una fábrica PSR-17; WiringPass resuelve ambos y decide
        // cuál de los dos transportes expone la interfaz SoapTransportInterface.
        $container->register('finkok_utils.psr18_request_handler', Psr18RequestHandler::class)
            ->setArguments([
                // Los sustituye WiringPass por las referencias reales.
                '$client' => null,
                '$requestFactory' => null,
            ])
            ->setPublic(false);

        $container->register('finkok_utils.transport', HttpClientSoapTransport::class)
            ->setArguments([
                new Reference('finkok_utils.symfony_request_handler'),
                $http['timeout'],
                // `finkok_utils.logger` siempre existe: WiringPass lo apunta al
                // logger de la aplicación o a un NullLogger.
                new Reference(WiringPass::LOGGER_SERVICE),
                $http['log_payloads'],
                $http['user_agent'],
            ])
            ->setPublic(false);

        $container->register('finkok_utils.psr18_transport', HttpClientSoapTransport::class)
            ->setArguments([
                new Reference('finkok_utils.psr18_request_handler'),
                $http['timeout'],
                new Reference(WiringPass::LOGGER_SERVICE),
                $http['log_payloads'],
                $http['user_agent'],
            ])
            ->setPublic(false);

        // Por omisión se expone el transporte de Symfony; WiringPass lo cambia a
        // PSR-18 cuando la aplicación registra un cliente PSR-18.
        $container->setAlias(SoapTransportInterface::class, 'finkok_utils.transport')
            ->setPublic(true);
    }

    private function registerServices(ContainerBuilder $container, int $xmlMaxAttempts): void
    {
        $container->register('finkok_utils.utilities_service', UtilitiesService::class)
            ->setArguments([
                // Se inyecta por **interfaz**, no por el id del transporte por
                // omisión: así WiringPass puede sustituir el transporte (por ejemplo
                // por el de PSR-18) en tiempo de compilación y el servicio lo respeta.
                // Es el único punto donde se decide qué transporte se usa.
                new Reference(SoapTransportInterface::class),
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

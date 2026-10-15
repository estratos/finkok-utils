<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\DependencyInjection;

use Estratos\FinkokUtilsBundle\Config\Credentials;
use Estratos\FinkokUtilsBundle\Config\CredentialsProvider;
use Estratos\FinkokUtilsBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokUtilsBundle\Config\Environment;
use Estratos\FinkokUtilsBundle\Contract\UtilitiesServiceInterface;
use Estratos\FinkokUtilsBundle\DependencyInjection\Compiler\WiringPass;
use Estratos\FinkokUtilsBundle\DependencyInjection\FinkokUtilsExtension;
use Estratos\FinkokUtilsBundle\Model\DateRange;
use Estratos\FinkokUtilsBundle\Soap\HttpClientSoapTransport;
use Estratos\FinkokUtilsBundle\Soap\SoapTransportInterface;
use Estratos\FinkokUtilsBundle\Tests\Fakes\RecordingPsr18Client;
use Estratos\FinkokUtilsBundle\Tests\Fixtures;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * El bundle no usa `ext-soap`: el envelope se escribe a mano a partir del WSDL.
 * Estas pruebas verifican que se pueda enviar con Symfony HttpClient o con un
 * cliente PSR-18, y que la elección siga la intención declarada por la aplicación.
 */
final class TransportSelectionTest extends TestCase
{
    private const PSR18_CLIENT_ID = 'app.psr18.client';

    private const PSR17_FACTORY_ID = 'app.psr17.factory';

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config = [], ?ContainerBuilder $container = null): ContainerBuilder
    {
        $container ??= new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        (new FinkokUtilsExtension())->load([$config], $container);
        (new WiringPass())->process($container);

        return $container;
    }

    private function registerCredentials(ContainerBuilder $container): void
    {
        $container->register(CredentialsProviderInterface::class, CredentialsProvider::class)
            ->setArguments([
                ['pruebas' => new Credentials('pruebas', 'usuario', 'secreto', 'EKU9003173C9', Environment::Demo)],
                'pruebas',
            ])
            ->setPublic(true);
    }

    /**
     * Registra un cliente PSR-18 y su fábrica PSR-17 como haría la aplicación.
     */
    private function registerPsr18(ContainerBuilder $container): void
    {
        $container->register(self::PSR18_CLIENT_ID, RecordingPsr18Client::class);
        $container->register(self::PSR17_FACTORY_ID, Psr17Factory::class);
    }

    private function exposedTransport(ContainerBuilder $container): string
    {
        return (string) $container->getAlias(SoapTransportInterface::class);
    }

    /**
     * Por omisión se usa Symfony HttpClient, que sí garantiza timeout y política de
     * reintentos.
     */
    public function testDefaultsToSymfonyTransport(): void
    {
        $container = $this->load();

        self::assertSame(WiringPass::TRANSPORT_SERVICE, $this->exposedTransport($container));
        self::assertFalse($container->hasDefinition(WiringPass::PSR18_TRANSPORT_SERVICE));
        self::assertFalse($container->hasDefinition('finkok_utils.psr18_request_handler'));
    }

    /**
     * El servicio `http_client` puede existir solo por tener FrameworkBundle, así
     * que no basta para cambiar de transporte.
     */
    public function testHttpClientServiceAloneDoesNotSwitchToPsr18(): void
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setPublic(true);

        $container = $this->load([], $container);

        self::assertSame(WiringPass::TRANSPORT_SERVICE, $this->exposedTransport($container));
        self::assertFalse($container->hasDefinition(WiringPass::PSR18_TRANSPORT_SERVICE));
    }

    /**
     * Con `psr18.client` declarado, el transporte cambia.
     */
    public function testExplicitPsr18ConfigurationSwitchesTransport(): void
    {
        $container = new ContainerBuilder();
        $this->registerPsr18($container);

        $container = $this->load([
            'psr18' => [
                'enabled' => true,
                'client' => self::PSR18_CLIENT_ID,
                'request_factory' => self::PSR17_FACTORY_ID,
            ],
        ], $container);

        self::assertSame(WiringPass::PSR18_TRANSPORT_SERVICE, $this->exposedTransport($container));
        self::assertTrue($container->hasDefinition(WiringPass::PSR18_TRANSPORT_SERVICE));
    }

    /**
     * También se detecta por tipo cuando la aplicación declara la intención con
     * `enabled: true`, sin necesidad de indicar ids.
     */
    public function testDetectsPsr18ServicesByTypeWhenEnabled(): void
    {
        $container = new ContainerBuilder();
        $container->register(ClientInterface::class, RecordingPsr18Client::class)->setPublic(true);
        $container->register(RequestFactoryInterface::class, Psr17Factory::class)->setPublic(true);

        $container = $this->load(['psr18' => ['enabled' => true]], $container);

        self::assertSame(WiringPass::PSR18_TRANSPORT_SERVICE, $this->exposedTransport($container));
    }

    /**
     * PSR-18 necesita una fábrica PSR-17 para construir la petición. Sin ella se cae
     * al transporte de Symfony en lugar de romper la compilación.
     */
    public function testPsr18ClientWithoutRequestFactoryFallsBack(): void
    {
        $container = new ContainerBuilder();
        $container->register(self::PSR18_CLIENT_ID, RecordingPsr18Client::class);

        $container = $this->load([
            'psr18' => ['enabled' => true, 'client' => self::PSR18_CLIENT_ID],
        ], $container);

        self::assertSame(WiringPass::TRANSPORT_SERVICE, $this->exposedTransport($container));
        self::assertFalse($container->hasDefinition(WiringPass::PSR18_TRANSPORT_SERVICE));
    }

    /**
     * Desactivar PSR-18 explícitamente debe ignorar los servicios presentes.
     */
    public function testPsr18CanBeDisabledExplicitly(): void
    {
        $container = new ContainerBuilder();
        $this->registerPsr18($container);

        $container = $this->load(['psr18' => ['enabled' => false]], $container);

        self::assertSame(WiringPass::TRANSPORT_SERVICE, $this->exposedTransport($container));
        self::assertFalse($container->hasDefinition(WiringPass::PSR18_TRANSPORT_SERVICE));
    }

    /**
     * Prueba de extremo a extremo: el servicio de utilerías envía el envelope por
     * PSR-18 y devuelve el DTO hidratado.
     */
    public function testFullUtilitiesCallOverPsr18(): void
    {
        $psrClient = new RecordingPsr18Client([Fixtures::content('report-total-success.xml')]);

        $container = new ContainerBuilder();
        $this->registerCredentials($container);
        $container->register(self::PSR18_CLIENT_ID, RecordingPsr18Client::class)
            ->setSynthetic(true)
            ->setPublic(true);
        $container->register(self::PSR17_FACTORY_ID, Psr17Factory::class);

        $container = $this->load([
            'psr18' => [
                'enabled' => true,
                'client' => self::PSR18_CLIENT_ID,
                'request_factory' => self::PSR17_FACTORY_ID,
            ],
        ], $container);
        $container->compile();
        $container->set(self::PSR18_CLIENT_ID, $psrClient);

        $result = $container->get(UtilitiesServiceInterface::class)
            ->reportTotal(DateRange::forMonth(2019, 1));

        self::assertTrue($result->isSuccess(), sprintf('error: %s', (string) $result->error()));
        self::assertSame(123, $result->total());
        self::assertSame(1, $psrClient->callCount());

        $request = $psrClient->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://demo-facturacion.finkok.com/servicios/soap/utilities',
            (string) $request->getUri(),
        );
        self::assertSame('"report_total"', $request->getHeaderLine('SOAPAction'));
        self::assertSame('text/xml; charset=utf-8', $request->getHeaderLine('Content-Type'));

        $body = $psrClient->lastBody();
        self::assertStringContainsString('<tns:report_total', $body);
        self::assertStringContainsString('<tns:date_from>2019-01-01T00:00:00</tns:date_from>', $body);
        self::assertStringContainsString('<tns:date_to>2019-01-31T23:59:59</tns:date_to>', $body);
    }

    /**
     * El bundle no debe depender de `ext-soap`.
     */
    public function testWorksWithoutExtSoapUsingMockHttpClient(): void
    {
        self::assertFalse(
            extension_loaded('soap'),
            'Esta prueba asume un entorno sin ext-soap, que es el escenario soportado.',
        );

        $recorded = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$recorded): \Symfony\Component\HttpClient\Response\MockResponse {
            $recorded[] = (string) ($options['body'] ?? '');

            return new \Symfony\Component\HttpClient\Response\MockResponse(
                Fixtures::content('datetime-success.xml'),
            );
        });

        $container = new ContainerBuilder();
        $this->registerCredentials($container);
        $container->register('http_client', MockHttpClient::class)->setSynthetic(true)->setPublic(true);

        $container = $this->load([], $container);
        $container->compile();
        $container->set('http_client', $client);

        $result = $container->get(UtilitiesServiceInterface::class)->datetime();

        self::assertTrue($result->isSuccess());
        self::assertSame('2026-10-14T18:25:31', $result->datetime());
        self::assertCount(1, $recorded);
        self::assertStringContainsString('<tns:datetime', $recorded[0]);
        self::assertStringContainsString('<tns:username>usuario</tns:username>', $recorded[0]);
    }

    public function testTransportIsResolvedByInterfaceForAutowiring(): void
    {
        $container = $this->load();
        $container->compile();

        self::assertInstanceOf(
            HttpClientSoapTransport::class,
            $container->get(SoapTransportInterface::class),
        );
    }

    /**
     * Regresión: el servicio de utilerías debe recibir el transporte **por
     * interfaz**. Cuando se inyectaba el id del transporte por omisión, cambiar a
     * PSR-18 no tenía efecto: el alias apuntaba al transporte nuevo pero el
     * servicio seguía usando Symfony HttpClient.
     */
    public function testUtilitiesServiceGetsTheTransportByInterface(): void
    {
        $container = $this->load();

        $arguments = $container->getDefinition('finkok_utils.utilities_service')->getArguments();
        $transport = $arguments[0];

        self::assertInstanceOf(Reference::class, $transport);
        self::assertSame(SoapTransportInterface::class, (string) $transport);
    }

    /**
     * La consecuencia práctica de la regresión anterior: con PSR-18 activado, el
     * servicio debe quedar enlazado al transporte PSR-18 tras compilar.
     */
    public function testUtilitiesServiceUsesPsr18TransportWhenEnabled(): void
    {
        $psrClient = new RecordingPsr18Client([Fixtures::content('report-total-success.xml')]);

        $container = new ContainerBuilder();
        $this->registerCredentials($container);
        $container->register(self::PSR18_CLIENT_ID, RecordingPsr18Client::class)
            ->setSynthetic(true)
            ->setPublic(true);
        $container->register(self::PSR17_FACTORY_ID, Psr17Factory::class);

        $container = $this->load([
            'psr18' => [
                'enabled' => true,
                'client' => self::PSR18_CLIENT_ID,
                'request_factory' => self::PSR17_FACTORY_ID,
            ],
        ], $container);
        $container->compile();
        $container->set(self::PSR18_CLIENT_ID, $psrClient);

        $service = $container->get(UtilitiesServiceInterface::class);

        $reflection = new \ReflectionObject($service);
        $property = $reflection->getProperty('transport');
        $property->setAccessible(true);

        self::assertInstanceOf(HttpClientSoapTransport::class, $property->getValue($service));

        // Y el transporte realmente instalado es el PSR-18: el handler debe
        // contener el cliente simulado.
        $transport = $property->getValue($service);
        $handlerProperty = (new \ReflectionObject($transport))->getProperty('handler');
        $handlerProperty->setAccessible(true);
        $handler = $handlerProperty->getValue($transport);

        self::assertInstanceOf(
            \Estratos\FinkokUtilsBundle\Soap\Psr18\Psr18RequestHandler::class,
            $handler,
        );

        $clientProperty = (new \ReflectionObject($handler))->getProperty('client');
        $clientProperty->setAccessible(true);

        self::assertSame($psrClient, $clientProperty->getValue($handler));
    }

    public function testPsr18RequestHandlerReceivesBothDependencies(): void
    {
        $container = new ContainerBuilder();
        $this->registerPsr18($container);

        $container = $this->load([
            'psr18' => [
                'enabled' => true,
                'client' => self::PSR18_CLIENT_ID,
                'request_factory' => self::PSR17_FACTORY_ID,
            ],
        ], $container);

        $definition = $container->getDefinition('finkok_utils.psr18_request_handler');

        $client = $definition->getArgument('$client');
        $factory = $definition->getArgument('$requestFactory');

        self::assertInstanceOf(Reference::class, $client);
        self::assertSame(self::PSR18_CLIENT_ID, (string) $client);
        self::assertInstanceOf(Reference::class, $factory);
        self::assertSame(self::PSR17_FACTORY_ID, (string) $factory);
    }
}

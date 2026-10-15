<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\DependencyInjection;

use Estratos\FinkokUtilsBundle\Config\Credentials;
use Estratos\FinkokUtilsBundle\Config\CredentialsProvider;
use Estratos\FinkokUtilsBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokUtilsBundle\Config\Environment;
use Estratos\FinkokUtilsBundle\Config\UnconfiguredCredentialsProvider;
use Estratos\FinkokUtilsBundle\Contract\UtilitiesServiceInterface;
use Estratos\FinkokUtilsBundle\DependencyInjection\Compiler\WiringPass;
use Estratos\FinkokUtilsBundle\DependencyInjection\Configuration;
use Estratos\FinkokUtilsBundle\DependencyInjection\FinkokUtilsExtension;
use Estratos\FinkokUtilsBundle\Exception\ConfigurationException;
use Estratos\FinkokUtilsBundle\Model\DateRange;
use Estratos\FinkokUtilsBundle\Service\UtilitiesService;
use Estratos\FinkokUtilsBundle\Soap\SoapTransportInterface;
use Estratos\FinkokUtilsBundle\Tests\Fixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * El bundle no lee credenciales de su configuración: los perfiles los crea la
 * aplicación y se inyectan como un servicio CredentialsProviderInterface.
 */
final class FinkokUtilsExtensionTest extends TestCase
{
    /**
     * Carga la extensión y ejecuta el compiler pass sin compilar.
     *
     * Se usa para inspeccionar las definiciones tal como las crea la extensión: al
     * compilar, Symfony inyecta los servicios privados en los públicos y los
     * renombra, de modo que los ids internos dejan de existir.
     *
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

    /**
     * @param array<string, mixed> $config
     */
    private function compile(array $config = [], ?ContainerBuilder $container = null): ContainerBuilder
    {
        $container = $this->load($config, $container);
        $container->compile();

        return $container;
    }

    /**
     * Registra el proveedor de credenciales como lo haría la aplicación.
     */
    private function registerAppCredentials(ContainerBuilder $container): void
    {
        $container->register('app.finkok.utils.credentials.matriz', Credentials::class)
            ->setArguments(['matriz', 'usuario@demo.com', 'clave-secreta', 'EKU9003173C9', Environment::Demo]);

        $container->register(CredentialsProviderInterface::class, CredentialsProvider::class)
            ->setArguments([
                ['matriz' => new Reference('app.finkok.utils.credentials.matriz')],
                'matriz',
            ])
            ->setPublic(true);
    }

    public function testLaConfiguracionDelBundleNoAdmiteCredenciales(): void
    {
        $children = array_keys((new Configuration())->getConfigTreeBuilder()->buildTree()->getChildren());
        sort($children);

        self::assertSame(['endpoints', 'http', 'xml_max_attempts'], $children);
        self::assertNotContains('profiles', $children);
        self::assertNotContains('username', $children);
        self::assertNotContains('password', $children);
    }

    public function testCompilaSinNingunaConfiguracionYExponeLosServicios(): void
    {
        $container = $this->compile();

        self::assertInstanceOf(UtilitiesService::class, $container->get(UtilitiesServiceInterface::class));
        self::assertInstanceOf(SoapTransportInterface::class, $container->get(SoapTransportInterface::class));
    }

    public function testSinProveedorRegistradoElContenedorCompilaPeroFallaAlUsarse(): void
    {
        $container = $this->compile();

        $provider = $container->get(CredentialsProviderInterface::class);

        self::assertInstanceOf(UnconfiguredCredentialsProvider::class, $provider);
        self::assertSame([], $provider->names());
        self::assertFalse($provider->has('matriz'));

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/CredentialsProviderInterface/');
        $provider->get();
    }

    public function testElServicioPropagaElMensajeCuandoNoHayCredenciales(): void
    {
        $container = $this->compile();
        $utilities = $container->get(UtilitiesServiceInterface::class);

        try {
            $utilities->datetime();
            self::fail('Se esperaba ConfigurationException.');
        } catch (ConfigurationException $exception) {
            self::assertStringContainsString('no ha registrado un proveedor de credenciales', $exception->getMessage());
            self::assertStringContainsString('README', $exception->getMessage());
        }
    }

    public function testUsaElProveedorDeCredencialesDeLaAplicacion(): void
    {
        $container = new ContainerBuilder();
        $this->registerAppCredentials($container);
        $container = $this->compile([], $container);

        $provider = $container->get(CredentialsProviderInterface::class);

        self::assertSame(['matriz'], $provider->names());
        self::assertSame('matriz', $provider->default()->name());
        self::assertSame(Environment::Demo, $provider->default()->environment());
    }

    public function testElProveedorDeLaAplicacionLlegaHastaLaPeticionHttp(): void
    {
        $recorded = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = ['url' => $url, 'body' => (string) ($options['body'] ?? '')];

            return new MockResponse(Fixtures::content('datetime-success.xml'));
        });

        $container = new ContainerBuilder();
        $this->registerAppCredentials($container);
        $container->register('http_client', MockHttpClient::class)->setSynthetic(true)->setPublic(true);
        $container = $this->compile([], $container);
        $container->set('http_client', $client);

        $result = $container->get(UtilitiesServiceInterface::class)->datetime('86000');

        self::assertTrue($result->isSuccess());
        self::assertSame('2026-10-14T18:25:31', $result->datetime());
        self::assertCount(1, $recorded);
        self::assertSame('https://demo-facturacion.finkok.com/servicios/soap/utilities', $recorded[0]['url']);
        self::assertStringContainsString('<tns:username>usuario@demo.com</tns:username>', $recorded[0]['body']);
        self::assertStringContainsString('<tns:zipcode>86000</tns:zipcode>', $recorded[0]['body']);
    }

    public function testAceptaUnAliasDeLaAplicacionComoProveedor(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.finkok.utils.credentials.matriz', Credentials::class)
            ->setArguments(['matriz', 'usuario@demo.com', 'clave-secreta', 'EKU9003173C9', Environment::Demo]);
        $container->register('app.credentials', CredentialsProvider::class)
            ->setArguments([['matriz' => new Reference('app.finkok.utils.credentials.matriz')], 'matriz']);
        $container->setAlias(CredentialsProviderInterface::class, 'app.credentials')->setPublic(true);

        $container = $this->compile([], $container);

        self::assertSame('matriz', $container->get(CredentialsProviderInterface::class)->default()->name());
    }

    public function testConectaElClienteHttpDeLaAplicacionCuandoExiste(): void
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setPublic(true);

        $factory = $this->load([], $container)->getDefinition(WiringPass::FACTORY_SERVICE);
        $base = $factory->getArgument('$base');

        self::assertInstanceOf(Reference::class, $base);
        self::assertSame('http_client', (string) $base);
    }

    public function testAplicaLosValoresPorDefectoDeLaInfraestructura(): void
    {
        $factory = $this->load()->getDefinition(WiringPass::FACTORY_SERVICE);

        self::assertSame(30.0, $factory->getArgument('$options')['timeout']);
        self::assertTrue($factory->getArgument('$retryEnabled'));
        self::assertSame(2, $factory->getArgument('$maxRetries'));
        self::assertNull(
            $factory->getArgument('$base'),
            'Sin el servicio http_client en la aplicación, la fábrica crea un cliente propio.',
        );
    }

    public function testRegistraUnLoggerNuloCuandoLaAplicacionNoTieneLogger(): void
    {
        $container = $this->load();

        self::assertTrue($container->hasDefinition(WiringPass::NULL_LOGGER_SERVICE));
        self::assertSame(WiringPass::NULL_LOGGER_SERVICE, (string) $container->getAlias(WiringPass::LOGGER_SERVICE));
    }

    public function testUsaElLoggerDeLaAplicacionCuandoExiste(): void
    {
        $container = new ContainerBuilder();
        $container->register('logger', \Psr\Log\NullLogger::class)->setPublic(true);

        $container = $this->load([], $container);

        self::assertSame('logger', (string) $container->getAlias(WiringPass::LOGGER_SERVICE));
    }

    public function testPermiteSobrescribirEndpointsYTimeout(): void
    {
        $container = $this->load([
            'endpoints' => ['utilities' => ['demo' => 'https://proxy.test/utilities']],
            'http' => ['timeout' => 12.5],
        ]);

        $endpoints = $container->getDefinition('finkok_utils.endpoint_resolver')->getArgument(0);

        self::assertSame('https://proxy.test/utilities', $endpoints['utilities']['demo']);
        self::assertSame(
            'https://facturacion.finkok.com/servicios/soap/utilities',
            $endpoints['utilities']['production'],
            'Los valores no sobrescritos conservan el endpoint oficial.',
        );
        self::assertSame(
            12.5,
            $container->getDefinition(WiringPass::FACTORY_SERVICE)->getArgument('$options')['timeout'],
        );
    }

    public function testConfiguraElTimeoutDelTransporteYElUserAgent(): void
    {
        $arguments = $this->load([
            'http' => ['timeout' => 7.5, 'user_agent' => 'mi-app/2.0', 'log_payloads' => true],
        ])->getDefinition('finkok_utils.transport')->getArguments();

        self::assertSame(7.5, $arguments[1]);
        self::assertTrue($arguments[3]);
        self::assertSame('mi-app/2.0', $arguments[4]);
    }

    public function testSePuedeDesactivarElReintentoAutomatico(): void
    {
        $factory = $this->load(['http' => ['retry' => ['enabled' => false, 'max_retries' => 0]]])
            ->getDefinition(WiringPass::FACTORY_SERVICE);

        self::assertFalse($factory->getArgument('$retryEnabled'));
        self::assertSame(0, $factory->getArgument('$maxRetries'));
    }

    public function testConfiguraLosIntentosDeDescargaDeXml(): void
    {
        $arguments = $this->load(['xml_max_attempts' => 5])
            ->getDefinition('finkok_utils.utilities_service')
            ->getArguments();

        self::assertSame(5, $arguments[4]);
    }

    public function testRechazaOpcionesDeConfiguracionDesconocidas(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $this->load(['profiles' => ['matriz' => ['username' => 'u', 'password' => 'p']]]);
    }

    public function testElFlujoCompletoDeUnReporteLlegaAlTransporte(): void
    {
        $recorded = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$recorded): MockResponse {
            $recorded[] = (string) ($options['body'] ?? '');

            return new MockResponse(Fixtures::content('report-uuid-success.xml'));
        });

        $container = new ContainerBuilder();
        $this->registerAppCredentials($container);
        $container->register('http_client', MockHttpClient::class)->setSynthetic(true)->setPublic(true);
        $container = $this->compile([], $container);
        $container->set('http_client', $client);

        $result = $container->get(UtilitiesServiceInterface::class)
            ->reportUuid(DateRange::forMonth(2026, 1));

        self::assertTrue($result->isSuccess());
        self::assertCount(2, $result->invoices());
        // El prefijo se declara en el propio elemento (xmlns:tns), no como atributo
        // separado, así que la aserción comprueba el nombre calificado.
        self::assertStringContainsString('<tns:report_uuid xmlns:tns="http://facturacion.finkok.com/utilities">', $recorded[0]);
        self::assertStringContainsString('<tns:date_from>2026-01-01T00:00:00</tns:date_from>', $recorded[0]);
        self::assertStringContainsString('<tns:date_to>2026-01-31T23:59:59</tns:date_to>', $recorded[0]);
    }
}

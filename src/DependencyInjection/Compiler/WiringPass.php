<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\DependencyInjection\Compiler;

use Estratos\FinkokUtilsBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokUtilsBundle\Config\UnconfiguredCredentialsProvider;
use Estratos\FinkokUtilsBundle\Soap\SoapTransportInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Completa el cableado del bundle en tiempo de compilación.
 *
 * Se hace en un compiler pass —y no al cargar la extensión— porque el orden de
 * carga de bundles no está garantizado: si este bundle se registra antes que
 * FrameworkBundle, los servicios `http_client` y `logger` todavía no existen.
 *
 * Además, deliberadamente no se usa IGNORE_ON_INVALID_REFERENCE dentro de arreglos
 * de argumentos: cuando la referencia no resuelve, Symfony elimina la entrada del
 * arreglo y desplaza las posiciones, lo que rompería los argumentos posicionales de
 * los constructores.
 */
final class WiringPass implements CompilerPassInterface
{
    /** Id interno que consumen los servicios del bundle. */
    public const CREDENTIALS_SERVICE = 'finkok_utils.credentials_provider';

    public const UNCONFIGURED_CREDENTIALS_SERVICE = 'finkok_utils.unconfigured_credentials_provider';

    public const FACTORY_SERVICE = 'finkok_utils.http_client_factory';

    public const LOGGER_SERVICE = 'finkok_utils.logger';

    public const NULL_LOGGER_SERVICE = 'finkok_utils.null_logger';

    /** Transporte por omisión, sobre Symfony HttpClient. */
    public const TRANSPORT_SERVICE = 'finkok_utils.transport';

    /** Transporte sobre un cliente PSR-18 (PSR-7 + PSR-17 + PSR-18). */
    public const PSR18_TRANSPORT_SERVICE = 'finkok_utils.psr18_transport';

    public function process(ContainerBuilder $container): void
    {
        $this->wireLogger($container);
        $this->wireHttpClient($container);
        $this->wirePsr18($container);
        $this->wireCredentialsProvider($container);
    }

    /**
     * Decide el transporte SOAP: Symfony HttpClient (por omisión) o PSR-18.
     *
     * PSR-18 **no contempla timeouts** (los configura el cliente), así que no se
     * activa solo: exige intención explícita mediante `finkok_utils.psr18.enabled`,
     * o bien que la aplicación registre un cliente PSR-18 y una fábrica PSR-17.
     * El servicio `http_client` de Symfony, en cambio, puede existir por el simple
     * hecho de tener FrameworkBundle instalado, de modo que no sirve como señal.
     *
     * Si la intención existe pero falta el cliente o la fábrica, se cae al
     * transporte de Symfony en lugar de romper la compilación del contenedor.
     */
    private function wirePsr18(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::PSR18_TRANSPORT_SERVICE)) {
            return;
        }

        /** @var array{enabled: bool, client: string|null, request_factory: string|null} $config */
        $config = $container->hasParameter('finkok_utils.psr18')
            ? $container->getParameter('finkok_utils.psr18')
            : ['enabled' => false, 'client' => null, 'request_factory' => null];

        // Sin intención explícita (ni `enabled` ni ids configurados) no se toca el
        // transporte: se queda Symfony HttpClient. Así un servicio que implemente
        // PSR-18 por casualidad no cambia el comportamiento del bundle.
        $explicit = $config['enabled'] || null !== $config['client'] || null !== $config['request_factory'];

        if (!$explicit) {
            $this->disablePsr18($container);

            return;
        }

        $client = $config['client'] ?? $this->findServiceOfType($container, ClientInterface::class);
        $factory = $config['request_factory'] ?? $this->findServiceOfType($container, RequestFactoryInterface::class);

        if (null === $client || null === $factory) {
            $this->disablePsr18($container);

            return;
        }

        $definition = $container->getDefinition('finkok_utils.psr18_request_handler');
        $definition->setArgument('$client', new Reference($client));
        $definition->setArgument('$requestFactory', new Reference($factory));

        $container->setAlias(SoapTransportInterface::class, self::PSR18_TRANSPORT_SERVICE)
            ->setPublic(true);
    }

    /**
     * Retira el transporte PSR-18 para que el contenedor no falle al compilar con
     * referencias a servicios inexistentes.
     */
    private function disablePsr18(ContainerBuilder $container): void
    {
        $container->removeDefinition(self::PSR18_TRANSPORT_SERVICE);
        $container->removeDefinition('finkok_utils.psr18_request_handler');
    }

    /**
     * Busca un servicio cuyo id o cuya clase declarada sea el tipo indicado.
     *
     * Se admite tanto el id de la interfaz (lo habitual con `services.yaml`) como la
     * clase concreta, para no exigir una convención de nombres a la aplicación.
     */
    private function findServiceOfType(ContainerBuilder $container, string $type): ?string
    {
        if ($container->has($type)) {
            return $type;
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if (null === $class) {
                continue;
            }

            // Se compara también por herencia/interfaz cuando la clase existe.
            if ($class === $type || (class_exists($class) && is_a($class, $type, true))) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Expone siempre un servicio finkok_utils.logger: el de la aplicación si
     * existe, o un NullLogger en caso contrario.
     */
    private function wireLogger(ContainerBuilder $container): void
    {
        if ($container->has('logger')) {
            $container->setAlias(self::LOGGER_SERVICE, 'logger')->setPublic(false);

            return;
        }

        $container->register(self::NULL_LOGGER_SERVICE, NullLogger::class)->setPublic(false);
        $container->setAlias(self::LOGGER_SERVICE, self::NULL_LOGGER_SERVICE)->setPublic(false);
    }

    /**
     * Reutiliza el cliente HTTP de la aplicación para compartir pool de conexiones,
     * proxy y CA configurados; si no existe, la fábrica crea uno.
     */
    private function wireHttpClient(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::FACTORY_SERVICE)) {
            return;
        }

        $container->getDefinition(self::FACTORY_SERVICE)->setArgument(
            '$base',
            $container->has('http_client') ? new Reference('http_client') : null,
        );
    }

    /**
     * Conecta el proveedor de credenciales que define la aplicación.
     *
     * Los perfiles no viven en la configuración del bundle: la aplicación registra
     * un servicio que implemente CredentialsProviderInterface y aquí se enlaza con
     * el servicio de utilerías.
     *
     * Si la aplicación no lo registra, se usa un proveedor que falla con un mensaje
     * accionable en cuanto se intente operar. Se prefiere eso a fallar en tiempo de
     * compilación, porque hay aplicaciones que instalan el bundle sin usarlo y su
     * contenedor no debe romperse por ello.
     */
    private function wireCredentialsProvider(ContainerBuilder $container): void
    {
        $appDefined = $container->hasDefinition(CredentialsProviderInterface::class)
            || $container->hasAlias(CredentialsProviderInterface::class);

        if ($appDefined) {
            $container->setAlias(self::CREDENTIALS_SERVICE, CredentialsProviderInterface::class)
                ->setPublic(false);

            return;
        }

        $container->register(self::UNCONFIGURED_CREDENTIALS_SERVICE, UnconfiguredCredentialsProvider::class)
            ->setPublic(false);

        $container->setAlias(self::CREDENTIALS_SERVICE, self::UNCONFIGURED_CREDENTIALS_SERVICE)
            ->setPublic(false);

        // También se expone la interfaz para que el autowiring falle con el mismo
        // mensaje en lugar de con un «service not found» de Symfony.
        $container->setAlias(CredentialsProviderInterface::class, self::UNCONFIGURED_CREDENTIALS_SERVICE)
            ->setPublic(true);
    }
}

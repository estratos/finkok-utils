<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\DependencyInjection;

use Estratos\FinkokUtilsBundle\Config\Environment;
use Estratos\FinkokUtilsBundle\Config\Service;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Árbol de configuración del bundle (clave raíz `finkok_utils`).
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('finkok_utils');
        $root = $treeBuilder->getRootNode();

        $root
            ->info('Web Service de utilerías de Finkok: fecha/hora, descarga de XML y reportes.')
            ->children()
                // Las credenciales NO se configuran en este árbol.
                // Los perfiles los crea la aplicación que consume los servicios y se
                // inyectan mediante un servicio CredentialsProviderInterface: pueden
                // venir de variables de entorno, de la base de datos o del inquilino
                // activo, y nunca deben quedar embebidos en la configuración del bundle.
                ->arrayNode('endpoints')
                    ->info('URLs de los servicios. Los valores por defecto apuntan a los hosts oficiales de Finkok.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode(Service::Utilities->value)
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('demo')->defaultValue(Service::Utilities->defaultEndpoint(Environment::Demo))->end()
                                ->scalarNode('production')->defaultValue(Service::Utilities->defaultEndpoint(Environment::Production))->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('http')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->floatNode('timeout')
                            ->defaultValue(30.0)
                            ->min(0)
                            ->info('Tiempo máximo, en segundos, de cada petición al Web Service.')
                        ->end()
                        ->booleanNode('log_payloads')
                            ->defaultFalse()
                            ->info('Escribe el envelope completo en el log. No lo actives en producción: contiene RFC, montos y, en get_xml, el CFDI completo.')
                        ->end()
                        ->scalarNode('user_agent')
                            ->defaultValue('finkok-utils/1.0')
                        ->end()
                        ->arrayNode('retry')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('enabled')
                                    ->defaultTrue()
                                    ->info('Reintenta errores de transporte y códigos transitorios. Es seguro porque todas las utilerías son de solo lectura.')
                                ->end()
                                ->integerNode('max_retries')->defaultValue(2)->min(0)->max(10)->end()
                                ->integerNode('delay_ms')->defaultValue(500)->min(0)->end()
                                ->floatNode('multiplier')->defaultValue(2.0)->min(1.0)->end()
                                ->integerNode('max_delay_ms')->defaultValue(0)->min(0)->end()
                                ->floatNode('jitter')->defaultValue(0.1)->min(0.0)->max(1.0)->end()
                                ->arrayNode('http_codes')
                                    ->info('Códigos que disparan reintento; 0 representa un error de transporte.')
                                    ->integerPrototype()->end()
                                    ->defaultValue([0, 429, 500, 502, 503, 504])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('xml_max_attempts')
                    ->defaultValue(3)
                    ->min(1)
                    ->max(10)
                    ->info(
                        'Intentos de get_xml cuando Finkok responde la ruta de un archivo en lugar del contenido. '
                        .'Ocurre con comprobantes fuera de la ventana de 3 meses.',
                    )
                ->end()
                ->arrayNode('psr18')
                    ->info(
                        'Transporte PSR-18. Por omisión se usa Symfony HttpClient, que sí garantiza timeout; '
                        .'PSR-18 delega el timeout en el cliente.',
                    )
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Envía el envelope SOAP con un cliente PSR-18 en lugar de Symfony HttpClient.')
                        ->end()
                        ->scalarNode('client')
                            ->defaultNull()
                            ->info(
                                'Id del servicio que implementa Psr\Http\Client\ClientInterface. '
                                .'Si se omite, se busca uno por tipo en el contenedor.',
                            )
                        ->end()
                        ->scalarNode('request_factory')
                            ->defaultNull()
                            ->info(
                                'Id del servicio que implementa Psr\Http\Message\RequestFactoryInterface (PSR-17). '
                                .'Es obligatorio con PSR-18: PSR-18 solo sabe enviar, no construir peticiones.',
                            )
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}

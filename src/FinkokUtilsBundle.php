<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle;

use Estratos\FinkokUtilsBundle\DependencyInjection\Compiler\WiringPass;
use Estratos\FinkokUtilsBundle\DependencyInjection\FinkokUtilsExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle de Symfony para consumir el Web Service de utilerías de Finkok.
 *
 * Servicios expuestos (autowireables por su interfaz):
 *  - {@see \Estratos\FinkokUtilsBundle\Contract\UtilitiesServiceInterface} fecha/hora, descarga de XML y reportes
 *  - {@see \Estratos\FinkokUtilsBundle\Config\CredentialsProviderInterface}      perfiles multi-RFC
 *  - {@see \Estratos\FinkokUtilsBundle\Soap\SoapTransportInterface}             transporte SOAP
 *
 * Registro en `config/bundles.php`:
 *
 * ```php
 * Estratos\FinkokUtilsBundle\FinkokUtilsBundle::class => ['all' => true],
 * ```
 */
final class FinkokUtilsBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new WiringPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->extension ??= new FinkokUtilsExtension();
    }
}

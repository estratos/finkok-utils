<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Config;

/**
 * Credenciales y datos fiscales con los que se consume el Web Service de
 * utilerías de Finkok.
 *
 * En Finkok cada RFC emisor se registra por separado en el panel, y los reportes
 * (`report_credit`, `report_total`, `report_uuid`) se piden **por RFC**. Por eso
 * un perfil agrupa: usuario, contraseña, ambiente y RFC emisor por defecto.
 *
 * A diferencia del bundle de cancelación, aquí no se requieren CSD: las
 * utilerías son de solo lectura.
 */
interface CredentialsInterface
{
    /**
     * Nombre del perfil en la configuración (por ejemplo `matriz` o `sucursal_norte`).
     */
    public function name(): string;

    /**
     * Usuario del panel de Finkok. Es el mismo para todos los RFC de la cuenta.
     */
    public function username(): string;

    /**
     * Contraseña del panel de Finkok.
     */
    public function password(): string;

    /**
     * RFC emisor por defecto del perfil. Es el valor que se envía como
     * `taxpayer_id` en los reportes cuando la llamada no lo especifica.
     */
    public function taxpayerId(): ?string;

    /**
     * RFC emisor, obligatorio.
     *
     * @throws \Estratos\FinkokUtilsBundle\Exception\ConfigurationException si no está configurado
     */
    public function requireTaxpayerId(): string;

    /**
     * Ambiente de Finkok al que apunta el perfil.
     */
    public function environment(): Environment;

    /**
     * URL de endpoint sobrescrita por el perfil, o `null` para usar la global.
     */
    public function endpoint(Service $service, Environment $environment): ?string;

    /**
     * Devuelve una copia del perfil apuntando a otro ambiente (útil para alternar
     * DEMO y PRODUCCIÓN sin duplicar la configuración).
     */
    public function withEnvironment(Environment $environment): static;
}

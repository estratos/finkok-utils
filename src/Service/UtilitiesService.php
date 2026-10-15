<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Service;

use Estratos\FinkokUtilsBundle\Config\CredentialsInterface;
use Estratos\FinkokUtilsBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokUtilsBundle\Config\EndpointResolver;
use Estratos\FinkokUtilsBundle\Config\Service;
use Estratos\FinkokUtilsBundle\Contract\UtilitiesServiceInterface;
use Estratos\FinkokUtilsBundle\Exception\ValidationException;
use Estratos\FinkokUtilsBundle\Hydrator\ReportCreditHydrator;
use Estratos\FinkokUtilsBundle\Hydrator\ReportTotalHydrator;
use Estratos\FinkokUtilsBundle\Hydrator\ReportUuidHydrator;
use Estratos\FinkokUtilsBundle\Hydrator\ScalarResultHydrator;
use Estratos\FinkokUtilsBundle\Model\DateRange;
use Estratos\FinkokUtilsBundle\Model\DateTimeResult;
use Estratos\FinkokUtilsBundle\Model\DownloadXmlResult;
use Estratos\FinkokUtilsBundle\Model\IdCcpResult;
use Estratos\FinkokUtilsBundle\Model\InvoiceType;
use Estratos\FinkokUtilsBundle\Model\MonedaResult;
use Estratos\FinkokUtilsBundle\Model\ReportCreditResult;
use Estratos\FinkokUtilsBundle\Model\ReportTotalResult;
use Estratos\FinkokUtilsBundle\Model\ReportUuidResult;
use Estratos\FinkokUtilsBundle\Soap\SoapTransportInterface;
use Psr\Log\LoggerInterface;

/**
 * Implementación del Web Service de utilerías de Finkok sobre SOAP document/literal.
 *
 * Notas de construcción del envelope:
 *
 *  - el `targetNamespace` del servicio es `http://facturacion.finkok.com/utilities`;
 *  - los tipos de **resultado** (`DateResult`, `XMLResult`, `IdCCPResult`, …) viven
 *    en el namespace compartido `apps.services.soap.core.views`, pero eso solo
 *    afecta a la lectura de respuestas;
 *  - el orden de los argumentos respeta la secuencia declarada en el WSDL, porque
 *    los servicios document/literal de Finkok la validan.
 */
final class UtilitiesService extends AbstractFinkokService implements UtilitiesServiceInterface
{
    /**
     * Intentos al descargar un XML que Finkok responde como ruta de archivo.
     *
     * El comportamiento está documentado por el propio PAC en el ticket #18950:
     * cuando el comprobante está fuera de la ventana de 3 meses, `get_xml` devuelve
     * la ruta del archivo en el servidor en lugar de su contenido, y unos cientos
     * de milisegundos después la descarga suele funcionar.
     */
    private const XML_MAX_ATTEMPTS = 3;

    /** Microsegundos entre reintentos de `get_xml`. */
    private const XML_RETRY_DELAY_MICROSECONDS = 200_000;

    public function __construct(
        SoapTransportInterface $transport,
        EndpointResolver $endpointResolver,
        CredentialsProviderInterface $credentialsProvider,
        ?LoggerInterface $logger = null,
        private readonly int $xmlMaxAttempts = self::XML_MAX_ATTEMPTS,
    ) {
        parent::__construct($transport, $endpointResolver, $credentialsProvider, $logger);
    }

    public function datetime(
        ?string $zipcode = null,
        ?CredentialsInterface $credentials = null,
    ): DateTimeResult {
        $credentials = $this->resolveCredentials($credentials);

        // Orden según el WSDL: username, password, zipcode.
        $arguments = [
            ...$this->authenticationArguments($credentials),
            'zipcode' => self::normalizeZipcode($zipcode),
        ];

        $response = $this->call('datetime', $arguments, $credentials);
        $result = ScalarResultHydrator::datetime($response->result('datetime'), $response->raw());

        $this->logger->info('Finkok utilerías: fecha y hora del servidor', [
            'perfil' => $credentials->name(),
            'ambiente' => $credentials->environment()->label(),
            'zipcode' => $zipcode,
            'datetime' => $result->datetime(),
            'error' => $result->error(),
        ]);

        return $result;
    }

    public function downloadXml(
        string $uuid,
        ?string $taxpayerId = null,
        ?InvoiceType $invoiceType = null,
        ?CredentialsInterface $credentials = null,
    ): DownloadXmlResult {
        $credentials = $this->resolveCredentials($credentials);
        $uuid = self::normalizeUuid($uuid);
        $taxpayerId = self::normalizeRfc($taxpayerId) ?? $credentials->taxpayerId();
        $invoiceType ??= InvoiceType::Ingreso;

        $this->assertUuid($uuid);

        // Orden según el WSDL: username, password, uuid, taxpayer_id, invoice_type.
        $arguments = [
            ...$this->authenticationArguments($credentials),
            'uuid' => $uuid,
            'taxpayer_id' => $taxpayerId,
            'invoice_type' => $invoiceType->value,
        ];

        $attempts = max(1, $this->xmlMaxAttempts);
        $result = null;

        for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
            $response = $this->call('get_xml', $arguments, $credentials);
            $result = ScalarResultHydrator::downloadXml($response->result('get_xml'), $response->raw());

            // Finkok a veces responde la ruta del archivo en lugar del contenido.
            // Reintentar es seguro porque la operación es de solo lectura.
            if (!$result->looksLikePath() || $attempt === $attempts) {
                break;
            }

            $this->logger->debug('Finkok utilerías: get_xml devolvió una ruta, se reintenta', [
                'uuid' => $uuid,
                'intento' => $attempt,
            ]);

            usleep(self::XML_RETRY_DELAY_MICROSECONDS);
        }

        // `$attempts >= 1` garantiza que el bucle se ejecutó al menos una vez.
        $result ??= new DownloadXmlResult();

        if ($result->looksLikePath()) {
            $this->logger->warning('Finkok utilerías: get_xml no devolvió el contenido del CFDI', [
                'uuid' => $uuid,
                'intentos' => $attempts,
                'sugerencia' => 'El comprobante probablemente está fuera de la ventana de 3 meses de Finkok.',
            ]);
        }

        $this->logger->info('Finkok utilerías: descarga de XML', [
            'perfil' => $credentials->name(),
            'uuid' => $uuid,
            'taxpayer_id' => $taxpayerId,
            'invoice_type' => $invoiceType->value,
            'recibido' => match (true) {
                $result->isXmlDocument() => 'xml',
                $result->looksLikePath() => 'ruta-no-contenido',
                default => 'vacío',
            },
            'error' => $result->error(),
        ]);

        return $result;
    }

    public function reportCredit(
        ?string $taxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): ReportCreditResult {
        $credentials = $this->resolveCredentials($credentials);
        $taxpayerId = self::normalizeRfc($taxpayerId) ?? $credentials->requireTaxpayerId();

        $this->assertRfc($taxpayerId);

        // Orden según el WSDL: username, password, taxpayer_id.
        $arguments = [
            ...$this->authenticationArguments($credentials),
            'taxpayer_id' => $taxpayerId,
        ];

        $response = $this->call('report_credit', $arguments, $credentials);
        $result = ReportCreditHydrator::hydrate($response->result('report_credit'), $response->raw());

        $this->logger->info('Finkok utilerías: reporte de créditos', [
            'perfil' => $credentials->name(),
            'taxpayer_id' => $taxpayerId,
            'movimientos' => $result->count(),
            'creditos' => $result->totalCredits(),
            'error' => $result->error(),
        ]);

        return $result;
    }

    public function reportTotal(
        DateRange $range,
        ?string $taxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): ReportTotalResult {
        $credentials = $this->resolveCredentials($credentials);
        $taxpayerId = self::normalizeRfc($taxpayerId) ?? $credentials->requireTaxpayerId();

        $this->assertRfc($taxpayerId);

        // Orden según el WSDL: username, password, taxpayer_id, date_from, date_to, invoice_type.
        $arguments = [
            ...$this->authenticationArguments($credentials),
            'taxpayer_id' => $taxpayerId,
            'date_from' => $range->fromString(),
            'date_to' => $range->toString(),
        ];

        $response = $this->call('report_total', $arguments, $credentials);
        $result = ReportTotalHydrator::hydrate($response->result('report_total'), $response->raw());

        $this->logger->info('Finkok utilerías: reporte de totales', [
            'perfil' => $credentials->name(),
            'taxpayer_id' => $taxpayerId,
            'rango' => (string) $range,
            'timbres' => $result->total(),
            'error' => $result->error(),
        ]);

        return $result;
    }

    public function reportUuid(
        DateRange $range,
        ?string $taxpayerId = null,
        ?CredentialsInterface $credentials = null,
    ): ReportUuidResult {
        $credentials = $this->resolveCredentials($credentials);
        $taxpayerId = self::normalizeRfc($taxpayerId) ?? $credentials->requireTaxpayerId();

        $this->assertRfc($taxpayerId);

        // Orden según el WSDL: username, password, taxpayer_id, date_from, date_to, invoice_type.
        $arguments = [
            ...$this->authenticationArguments($credentials),
            'taxpayer_id' => $taxpayerId,
            'date_from' => $range->fromString(),
            'date_to' => $range->toString(),
        ];

        $response = $this->call('report_uuid', $arguments, $credentials);
        $result = ReportUuidHydrator::hydrate($response->result('report_uuid'), $response->raw());

        $this->logger->info('Finkok utilerías: reporte de UUID', [
            'perfil' => $credentials->name(),
            'taxpayer_id' => $taxpayerId,
            'rango' => (string) $range,
            'comprobantes' => $result->count(),
            'error' => $result->error(),
        ]);

        return $result;
    }

    public function getIdCcp(?CredentialsInterface $credentials = null): IdCcpResult
    {
        $credentials = $this->resolveCredentials($credentials);

        // Orden según el WSDL: username, password.
        $arguments = $this->authenticationArguments($credentials);

        $response = $this->call('get_idccp', $arguments, $credentials);
        $result = ScalarResultHydrator::idCcp($response->result('get_idccp'), $response->raw());

        $this->logger->info('Finkok utilerías: identificador de Carta Porte', [
            'perfil' => $credentials->name(),
            'idccp' => $result->idccp(),
            'error' => $result->error(),
        ]);

        return $result;
    }

    public function getMonedaUsd(
        ?string $date = null,
        ?CredentialsInterface $credentials = null,
    ): MonedaResult {
        $credentials = $this->resolveCredentials($credentials);

        // Orden según el WSDL: username, password, date.
        $arguments = [
            ...$this->authenticationArguments($credentials),
            'date' => null === $date ? null : trim($date),
        ];

        $response = $this->call('get_moneda_usd', $arguments, $credentials);
        $result = ScalarResultHydrator::moneda($response->result('get_moneda_usd'), $response->raw());

        $this->logger->info('Finkok utilerías: tipo de cambio USD', [
            'perfil' => $credentials->name(),
            'fecha' => $date,
            'moneda' => $result->moneda(),
            'error' => $result->error(),
        ]);

        return $result;
    }

    protected function service(): Service
    {
        return Service::Utilities;
    }

    /**
     * El WSDL declara `zipcode` con el patrón `[0-9]{5}`. Enviar otra cosa produce
     * un SOAP Fault de validación de esquema, así que se valida en local.
     */
    private static function normalizeZipcode(?string $zipcode): ?string
    {
        if (null === $zipcode || '' === trim($zipcode)) {
            return null;
        }

        $zipcode = trim($zipcode);

        if (1 !== preg_match('/^[0-9]{5}$/', $zipcode)) {
            throw ValidationException::forInvalidZipcode($zipcode);
        }

        return $zipcode;
    }

    private static function normalizeUuid(string $uuid): string
    {
        $uuid = strtoupper(trim($uuid));

        if ('' === $uuid) {
            throw ValidationException::forEmptyArgument('uuid');
        }

        return $uuid;
    }

    private static function normalizeRfc(?string $rfc): ?string
    {
        if (null === $rfc) {
            return null;
        }

        $rfc = strtoupper(trim($rfc));

        return '' === $rfc ? null : $rfc;
    }

    private function assertUuid(string $uuid): void
    {
        if (1 !== preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/', $uuid)) {
            throw ValidationException::forInvalidUuid($uuid);
        }
    }

    private function assertRfc(string $rfc): void
    {
        if (1 !== preg_match('/^[A-ZÑ&]{3,4}[0-9]{2}[0-1][0-9][0-3][0-9][A-Z0-9]{3}$/', $rfc)) {
            throw ValidationException::forInvalidRfc($rfc);
        }
    }
}

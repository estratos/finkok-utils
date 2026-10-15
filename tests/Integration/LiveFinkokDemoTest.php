<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Integration;

use Estratos\FinkokUtilsBundle\Config\Credentials;
use Estratos\FinkokUtilsBundle\Config\CredentialsProvider;
use Estratos\FinkokUtilsBundle\Config\EndpointResolver;
use Estratos\FinkokUtilsBundle\Config\Environment;
use Estratos\FinkokUtilsBundle\Http\HttpClientFactory;
use Estratos\FinkokUtilsBundle\Model\DateRange;
use Estratos\FinkokUtilsBundle\Service\UtilitiesService;
use Estratos\FinkokUtilsBundle\Soap\HttpClientSoapTransport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas de contrato contra el ambiente de demostración real de Finkok.
 *
 * Están excluidas de la suite por defecto (grupo `live`) porque dependen de la red
 * y de credenciales del panel de Finkok:
 *
 * ```bash
 * FINKOK_UTILS_USERNAME=usuario FINKOK_UTILS_PASSWORD=clave composer test:live
 * ```
 *
 * El objetivo de estas pruebas no es cubrir lógica —eso lo hace la suite unitaria
 * contra fixtures— sino detectar que Finkok cambió el WSDL: un rename de operación,
 * un argumento nuevo o un namespace distinto.
 */
#[Group('live')]
final class LiveFinkokDemoTest extends TestCase
{
    private ?UtilitiesService $service = null;

    private string $username = '';

    private string $password = '';

    private string $taxpayerId = '';

    protected function setUp(): void
    {
        $this->username = (string) ($_SERVER['FINKOK_UTILS_USERNAME'] ?? getenv('FINKOK_UTILS_USERNAME') ?: '');
        $this->password = (string) ($_SERVER['FINKOK_UTILS_PASSWORD'] ?? getenv('FINKOK_UTILS_PASSWORD') ?: '');
        $this->taxpayerId = (string) ($_SERVER['FINKOK_UTILS_TAXPAYER_ID'] ?? getenv('FINKOK_UTILS_TAXPAYER_ID') ?: '');

        if ('' === $this->username || '' === $this->password) {
            self::markTestSkipped(
                'Define FINKOK_UTILS_USERNAME y FINKOK_UTILS_PASSWORD para ejecutar las pruebas live.',
            );
        }

        $credentials = new Credentials(
            'live',
            $this->username,
            $this->password,
            '' === $this->taxpayerId ? null : $this->taxpayerId,
            Environment::Demo,
        );

        $this->service = new UtilitiesService(
            transport: new HttpClientSoapTransport(
                (new HttpClientFactory(options: ['timeout' => 20.0], retryEnabled: false))->create(),
                timeout: 20.0,
            ),
            endpointResolver: new EndpointResolver(),
            credentialsProvider: new CredentialsProvider([$credentials]),
        );
    }

    private function service(): UtilitiesService
    {
        self::assertNotNull($this->service);

        return $this->service;
    }

    private function requireTaxpayerId(): string
    {
        if ('' === $this->taxpayerId) {
            self::markTestSkipped('Define FINKOK_UTILS_TAXPAYER_ID para ejecutar esta prueba.');
        }

        return $this->taxpayerId;
    }

    public function testDatetimeReturnsAUsableServerTime(): void
    {
        $result = $this->service()->datetime();

        if (!$result->isSuccess()) {
            // Credenciales de demo inválidas o el servicio devolvió un error de negocio.
            self::fail(sprintf('datetime falló contra el demo: %s', $result->error() ?? '(sin detalle)'));
        }

        $dateTime = $result->toDateTime(new \DateTimeZone('America/Mexico_City'));

        self::assertInstanceOf(\DateTimeImmutable::class, $dateTime);
        // La hora del servidor no debería desviarse más de un día de la hora local.
        self::assertLessThan(
            86400,
            abs($dateTime->getTimestamp() - time()),
            'La hora devuelta por Finkok está a más de un día de la hora local.',
        );
    }

    public function testDatetimeAcceptsAPostalCode(): void
    {
        $result = $this->service()->datetime('86000');

        if (!$result->isSuccess()) {
            self::fail(sprintf('datetime con zipcode falló: %s', $result->error() ?? '(sin detalle)'));
        }

        self::assertTrue($result->hasDatetime());
    }

    public function testReportCreditReturnsAUsableResult(): void
    {
        $result = $this->service()->reportCredit($this->requireTaxpayerId());

        if ($result->isError()) {
            self::fail(sprintf('report_credit falló: %s', (string) $result->error()));
        }

        // Un RFC sin movimientos es un resultado válido: el reporte llega vacío.
        self::assertSame($result->count(), \count($result->items()));
        self::assertSame($result->totalCredits(), array_sum(array_map(
            static fn ($item): int => $item->creditAsInt() ?? 0,
            $result->items(),
        )));
    }

    public function testReportTotalReturnsAUsableResult(): void
    {
        $result = $this->service()->reportTotal(
            DateRange::forMonth((int) date('Y'), (int) date('n')),
            $this->requireTaxpayerId(),
        );

        if ($result->isError()) {
            self::fail(sprintf('report_total falló: %s', (string) $result->error()));
        }

        self::assertGreaterThanOrEqual(0, $result->total());
    }

    public function testReportUuidReturnsAUsableResult(): void
    {
        $result = $this->service()->reportUuid(
            DateRange::forMonth((int) date('Y'), (int) date('n')),
            $this->requireTaxpayerId(),
        );

        if ($result->isError()) {
            self::fail(sprintf('report_uuid falló: %s', (string) $result->error()));
        }

        foreach ($result->invoices() as $invoice) {
            self::assertNotNull($invoice->uuid);
            self::assertMatchesRegularExpression(
                '/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/i',
                (string) $invoice->uuid,
            );
        }
    }

    /**
     * `get_idccp` es la operación menos documentada del WSDL: no aparece en la
     * documentación pública de Finkok. Lo que se verifica es que exista y sea
     * invocable —es decir, que Finkok no responda un SOAP Fault por «operación
     * desconocida»—, no el valor concreto, que depende de la cuenta.
     */
    public function testGetIdCcpIsInvokableWithoutASoapFault(): void
    {
        $result = $this->service()->getIdCcp();

        self::assertInstanceOf(
            \Estratos\FinkokUtilsBundle\Model\IdCcpResult::class,
            $result,
            'La operación get_idccp debe responder un IdCCPResult y no lanzar un SOAP Fault.',
        );
    }
}

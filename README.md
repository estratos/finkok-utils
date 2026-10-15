# FinkokUtilsBundle

Bundle de **Symfony 7.4** que adapta el **Web Service de utilerías de Finkok**
(`utilities.wsdl`): fecha y hora del servidor, descarga de CFDI timbrados y
reportes de créditos, totales y UUID.

No depende de `ext-soap`: construye el envelope SOAP 1.1 a mano a partir del WSDL
real y lo envía con Symfony HttpClient **o con cualquier cliente PSR-18**.

| Web Service | Demo | Productivo |
|---|---|---|
| WSDL | `https://demo-facturacion.finkok.com/servicios/soap/utilities.wsdl` | `https://facturacion.finkok.com/servicios/soap/utilities.wsdl` |
| Endpoint | `https://demo-facturacion.finkok.com/servicios/soap/utilities` | `https://facturacion.finkok.com/servicios/soap/utilities` |

## Métodos cubiertos

El WSDL expone siete operaciones; las cinco que documenta Finkok más dos que
declara el contrato:

| Operación WSDL | Método del bundle | Resultado | Para qué sirve |
|---|---|---|---|
| `datetime` | `datetime()` | `DateTimeResult` | Hora oficial del PAC; evita el rechazo `CFDI40147` por desfase de reloj |
| `get_xml` | `downloadXml()` | `DownloadXmlResult` | Recupera un CFDI timbrado por UUID (ventana de 3 meses) |
| `report_credit` | `reportCredit()` | `ReportCreditResult` | Créditos (timbres) añadidos a la cuenta |
| `report_total` | `reportTotal()` | `ReportTotalResult` | Comprobantes timbrados en un rango de fechas |
| `report_uuid` | `reportUuid()` | `ReportUuidResult` | Folios Fiscales emitidos en un rango de fechas |
| `get_idccp` | `getIdCcp()` | `IdCcpResult` | Identificador del complemento Carta Porte |
| `get_moneda_usd` | `getMonedaUsd()` | `MonedaResult` | Tipo de cambio del dólar |

`get_idccp` y `get_moneda_usd` no aparecen en la documentación pública de Finkok
pero **sí están declaradas** en `utilities.wsdl`, así que se implementan con los
argumentos que el propio contrato define.

## Requisitos

- PHP 8.2 o superior (`ext-dom`, `ext-libxml`)
- Symfony 7.4
- **No requiere `ext-soap`**

Depende de las interfaces PSR (`psr/http-client`, `psr/http-factory`,
`psr/http-message`) y de `estratos/finkok-bundle`, que aporta el contrato compartido
de credenciales (`CredentialsInterface`, `CredentialsProviderInterface`) y los
valores de ambiente, para que ambos bundles sean interoperables.

Si usas el transporte PSR-18 necesitas además una implementación PSR-7/PSR-17
(por ejemplo `nyholm/psr7` o `guzzlehttp/psr7`); con el transporte por omisión no
hace falta ninguna.

## Instalación

```bash
composer require estratos/finkok-utils
```

Registra el bundle en `config/bundles.php`:

```php
return [
    // …
    Estratos\FinkokUtilsBundle\FinkokUtilsBundle::class => ['all' => true],
];
```

## Configuración

El bundle **no lee credenciales de su propia configuración**: los perfiles los
crea la aplicación y se inyectan como un servicio que implemente
`CredentialsProviderInterface`. Así los secretos pueden venir de variables de
entorno, de la base de datos o del inquilino activo, sin quedar embebidos en YAML.

### 1. Perfiles de credenciales

```php
// src/Config/FinkokUtilsCredentialsProvider.php
namespace App\Config;

use Estratos\FinkokUtilsBundle\Config\Credentials;
use Estratos\FinkokUtilsBundle\Config\CredentialsProvider;
use Estratos\FinkokUtilsBundle\Config\CredentialsProviderInterface;
use Estratos\FinkokUtilsBundle\Config\Environment;

final class FinkokUtilsCredentialsProvider implements CredentialsProviderInterface
{
    private CredentialsProvider $inner;

    public function __construct(string $username, string $password, string $rfc)
    {
        $this->inner = new CredentialsProvider([
            'matriz' => new Credentials(
                name: 'matriz',
                username: $username,
                password: $password,
                taxpayerId: $rfc,
                environment: Environment::Production,
            ),
        ]);
    }

    public function get(?string $name = null): \Estratos\FinkokUtilsBundle\Config\CredentialsInterface
    {
        return $this->inner->get($name);
    }

    public function has(string $name): bool
    {
        return $this->inner->has($name);
    }

    public function default(): \Estratos\FinkokUtilsBundle\Config\CredentialsInterface
    {
        return $this->inner->default();
    }

    public function names(): array
    {
        return $this->inner->names();
    }
}
```

```yaml
# config/services.yaml
services:
    Estratos\FinkokUtilsBundle\Config\CredentialsProviderInterface:
        class: App\Config\FinkokUtilsCredentialsProvider
        arguments:
            $username: '%env(FINKOK_USERNAME)%'
            $password: '%env(FINKOK_PASSWORD)%'
            $rfc: '%env(FINKOK_RFC)%'
```

Si la aplicación no registra un proveedor, el contenedor **compila igual** y el
bundle falla con un mensaje accionable en cuanto se intenta usar un servicio.

### 2. Opciones del bundle (opcionales)

```yaml
# config/packages/finkok_utils.yaml
finkok_utils:
    endpoints:
        utilities:
            demo: 'https://demo-facturacion.finkok.com/servicios/soap/utilities'
            production: 'https://facturacion.finkok.com/servicios/soap/utilities'
    http:
        timeout: 30.0
        log_payloads: false   # nunca lo actives en producción
        user_agent: 'mi-app/1.0'
        retry:
            enabled: true     # seguro: todas las utilerías son de solo lectura
            max_retries: 2
    xml_max_attempts: 3       # reintentos de get_xml cuando Finkok devuelve una ruta
    psr18:
        enabled: false        # true para enviar con un cliente PSR-18
        client: null          # id del servicio PSR-18; null = autodetectar
        request_factory: null # id del servicio PSR-17; obligatorio con PSR-18
```

### 3. Transporte: Symfony HttpClient o PSR-18

El bundle **no usa `ext-soap`**: escribe el envelope SOAP 1.1 a mano a partir del
WSDL real y lo envía por POST con `Content-Type: text/xml` y la cabecera
`SOAPAction`. El envío concreto es intercambiable.

**Por omisión usa Symfony HttpClient** (`symfony/http-client`), que es el que
garantiza timeout y política de reintentos. Si prefieres tu propio cliente
**PSR-18** (Guzzle, `php-http`, o `symfony/http-client` mediante un adaptador),
actívalo así:

```yaml
# config/services.yaml
services:
    app.psr18.client:
        class: GuzzleHttp\Client
        arguments:
            - timeout: 30.0
              connect_timeout: 10.0

    app.psr17.factory:
        class: Nyholm\Psr7\Factory\Psr17Factory
```

```yaml
# config/packages/finkok_utils.yaml
finkok_utils:
    psr18:
        enabled: true
        client: app.psr18.client
        request_factory: app.psr17.factory
```

Con `enabled: true` puedes omitir los ids y el bundle los autodetecta por tipo
(`Psr\Http\Client\ClientInterface` y `Psr\Http\Message\RequestFactoryInterface`).

**PSR-17 es obligatorio con PSR-18**: PSR-18 solo sabe *enviar* peticiones, no
construirlas, así que hace falta una fábrica de peticiones. Si activas PSR-18 sin
fábrica, el bundle lo detecta y **se queda con Symfony HttpClient** en lugar de
romper la compilación del contenedor.

> **Sobre los timeouts:** PSR-18 no contempla timeouts, los configura el cliente.
> Por eso el transporte PSR-18 ignora la opción `finkok_utils.http.timeout` y
> tampoco aplica la política de reintentos. Si necesitas ambas cosas, usa el
> transporte por omisión o configura el timeout en tu cliente PSR-18.

Puedes ajustar el transporte por completo implementando `RequestHandlerInterface`:

```php
use Estratos\FinkokUtilsBundle\Soap\Http\RequestHandlerInterface;
use Estratos\FinkokUtilsBundle\Soap\Http\HttpResponse;

final class MiHandler implements RequestHandlerInterface
{
    public function post(string $url, string $body, array $headers, float $timeout): HttpResponse
    {
        // … tu propio envío; devuelve el estado, el cuerpo y las cabeceras.
    }
}
```

## Uso

```php
use Estratos\FinkokUtilsBundle\Contract\UtilitiesServiceInterface;
use Estratos\FinkokUtilsBundle\Model\DateRange;

final class ConciliacionController
{
    public function __construct(private UtilitiesServiceInterface $utils) {}

    public function resumen(): void
    {
        // Hora oficial del PAC.
        $ahora = $this->utils->datetime()->toDateTime('America/Mexico_City');

        // Comprobantes timbrados en marzo de 2026.
        $rango = DateRange::forMonth(2026, 3);
        $reporte = $this->utils->reportUuid($rango);

        foreach ($reporte->invoices() as $comprobante) {
            printf("%s  %s%n", $comprobante->uuid, $comprobante->date);
        }

        // Descarga del XML de un comprobante concreto.
        $xml = $this->utils->downloadXml('12345678-1234-1234-1234-000000000001');

        if ($xml->looksLikePath()) {
            // El comprobante está fuera de la ventana de 3 meses de Finkok.
        } elseif ($xml->isXmlDocument()) {
            file_put_contents('cfdi.xml', (string) $xml->xml());
        }
    }
}
```

### Selección de perfil

Todas las llamadas aceptan un perfil explícito. En aplicaciones multi-RFC y en
workers de larga vida (Messenger, RoadRunner, Swoole) **siempre** pásalo
explícitamente: el estado global provoca que un reporte se consulte con el RFC
equivocado.

```php
$perfil = $provider->get('sucursal_norte');

$this->utils->reportTotal(DateRange::forMonth(2026, 3), credentials: $perfil);
```

Una llamada puede sobrescribir el RFC del perfil sin cambiar de credenciales:

```php
$this->utils->reportUuid($rango, taxpayerId: 'URE180429TM6');
```

## Detalles de la implementación

### Namespaces

`utilities.wsdl` declara **dos** esquemas con namespaces distintos:

- el esquema del servicio, `http://facturacion.finkok.com/utilities`, donde viven
  los elementos de entrada y salida de cada operación (`datetime`,
  `datetimeResult`, `report_uuidResult`, …);
- el esquema compartido `apps.services.soap.core.views`, donde viven los tipos de
  resultado complejos (`ReportUUID`, `ReportTotalCredit`, …).

El bundle emite cada elemento en su namespace declarado, tal como lo haría un
cliente generado a partir del WSDL. Confundirlos produce respuestas vacías o la
incidencia 738.

### Errores de negocio

A diferencia de los servicios de timbrado, utilerías **no** usa el arreglo
`<Incidencias>`: los errores llegan en texto plano dentro del nodo `error` de cada
resultado.

```php
$resultado = $this->utils->datetime();

if ($resultado->isError()) {
    // "El usuario o contraseña son inválidos"
    throw new \RuntimeException((string) $resultado->error());
}
```

Un `error` no nulo no siempre significa fallo: algunos métodos devuelven un texto
informativo junto con datos válidos. Usa `isError()` para decidir.

### `report_total` y `report_uuid` devuelven listas vacías ante un rango inválido

El WSDL declara `date_from` y `date_to` como `xs:dateTime` **obligatorios**, y el
servicio **no** valida el rango: si las fechas son incorrectas responde una lista
vacía sin explicar el motivo.

Por eso el bundle valida localmente mediante `DateRange`, que además resuelve un
detalle importante: un rango «hasta el 31 de enero» debe enviarse como
`2026-01-31T23:59:59`, porque enviar `T00:00:00` deja fuera todos los
comprobantes emitidos ese día.

```php
DateRange::forMonth(2026, 1);            // 01-01T00:00:00 → 01-31T23:59:59
DateRange::forDay('2026-10-14');          // día completo
DateRange::forPeriods(2026, 1, 2026, 3);  // varios meses
DateRange::fromStrings('2026-01-01', '2026-01-31');
DateRange::between($desde, $hasta);
```

### `get_xml` y la ventana de 3 meses

Finkok sólo conserva el XML de los **últimos 3 meses**. Cuando el comprobante es
más antiguo, `get_xml` devuelve la **ruta del archivo** en el servidor en lugar de
su contenido (ticket #18950 de Finkok).

El bundle detecta ese caso, reintenta hasta `xml_max_attempts` veces (Finkok suele
responder el contenido en el siguiente intento) y, si insiste, lo expone
explícitamente en vez de devolver una cadena que podrías guardar como si fuera un
CFDI:

```php
if ($xml->looksLikePath()) {
    // No disponible: fuera de la ventana de retención.
} elseif ($xml->isXmlDocument()) {
    // Contenido real del CFDI.
}
```

### Fechas con fracciones de segundo

Los reportes devuelven `xs:dateTime` con una precisión variable
(`2019-01-13T14:15:16.171819`). PHP rechaza más de seis decimales, así que el
acceso a `DateTimeImmutable` trunca la fracción de forma transparente:

```php
$item->dateTime('America/Mexico_City'); // ?DateTimeImmutable
```

## Servicios del contenedor

| Id | Interfaz autowireable | Descripción |
|---|---|---|
| `finkok_utils.utilities_service` | `UtilitiesServiceInterface` | Adaptador de utilerías |
| `finkok_utils.transport` | `SoapTransportInterface` | Transporte SOAP sobre Symfony HttpClient |
| `finkok_utils.psr18_transport` | — | Transporte SOAP sobre PSR-18 (si está activado) |
| `finkok_utils.endpoint_resolver` | — | Resolución de endpoints |
| `finkok_utils.credentials_provider` | `CredentialsProviderInterface` | Perfiles |

El servicio de utilerías recibe el transporte **por interfaz**, de modo que
`SoapTransportInterface` es el único punto donde se decide cuál se usa. Las
interfaces están marcadas como públicas, así que puedes decorarlas o sustituirlas
sin tocar los identificadores internos.

## Pruebas

```bash
composer test          # suite unitaria (sin red)
composer test:live     # contrato contra el demo real de Finkok
```

Las pruebas unitarias **no tocan la red**: usan fixtures con respuestas SOAP reales
de Finkok y un cliente PSR-18 simulado, así que se ejecutan sin credenciales y sin
`ext-soap`.

Las pruebas live requieren credenciales:

```bash
FINKOK_UTILS_USERNAME=usuario \
FINKOK_UTILS_PASSWORD=clave \
FINKOK_UTILS_TAXPAYER_ID=EKU9003173C9 \
composer test:live
```

## Documentación adicional

Los documentos de referencia no se incluyen en el paquete de Composer (para que la
instalación sea mínima); están en el repositorio:

- [`docs/wsdl.md`](https://github.com/estratos/finkok-utils/blob/main/docs/wsdl.md)
  — contrato completo extraído de `utilities.wsdl`
- [`docs/errores.md`](https://github.com/estratos/finkok-utils/blob/main/docs/errores.md)
  — diagnóstico de fallos frecuentes

## Licencia

MIT. Consulta [LICENSE](LICENSE).

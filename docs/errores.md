# Diagnóstico de errores

Guía de los fallos más frecuentes al consumir el Web Service de utilerías de
Finkok y cómo distinguirlos.

## Cómo se clasifican los errores

El bundle separa tres capas, cada una con su excepción:

| Capa | Excepción | Significado |
|---|---|---|
| Entrada local | `ValidationException` | Los datos no son utilizables; **no se llamó a Finkok** |
| Configuración | `ConfigurationException` / `ProfileNotFoundException` | Falta un perfil o un RFC |
| Transporte | `TransportException` | Red, DNS, TLS, timeout, HTTP 4xx/5xx |
| Protocolo | `SoapFaultException` | Finkok respondió `soap:Fault` |
| Respuesta | `UnexpectedResponseException` | El cuerpo no es un envelope SOAP interpretable |
| Negocio | *(sin excepción)* | Llega en `error()` del DTO de resultado |

Todas implementan `FinkokExceptionInterface`, así que puedes capturarlas con un
solo `catch` si no necesitas distinguirlas:

```php
use Estratos\FinkokUtilsBundle\Exception\FinkokExceptionInterface;

try {
    $reporte = $this->utils->reportUuid($rango);
} catch (FinkokExceptionInterface $e) {
    // Fallo técnico: red, protocolo, configuración o validación local.
}
```

Los errores de **negocio** no lanzan excepción: viajan en el nodo `error` de una
respuesta correcta.

```php
$resultado = $this->utils->datetime();

if ($resultado->isError()) {
    $this->logger->warning('Finkok rechazó la operación', ['error' => $resultado->error()]);
}
```

## Errores frecuentes

### «El usuario o contraseña son inválidos»

Llega como texto en `error()`, **no** como excepción.

Causas, en orden de frecuencia:

1. **Credenciales de un ambiente contra el otro.** Finkok expone dos entornos
   totalmente independientes, cada uno con su propio panel y sus propios usuarios.
   Usar credenciales de producción contra `demo-facturacion.finkok.com` produce
   exactamente este mensaje. Revisa `Credentials::environment()`.
2. Contraseña rotada en el panel y no actualizada en la configuración.
3. Usuario dado de baja o suspendido.

```php
$perfil->environment(); // Environment::Demo | Environment::Production
```

### Lista vacía en `report_total` o `report_uuid`

Finkok **no** valida el rango de fechas: responde una lista vacía sin explicar el
motivo. Es el fallo más difícil de diagnosticar de todo el WSDL.

Causa habitual: enviar `date_to` con hora `T00:00:00`. Un reporte «hasta el 31 de
enero» con `date_to=2026-01-31T00:00:00` deja fuera **todos** los comprobantes
emitidos ese día.

El bundle lo evita con `DateRange`, que extiende automáticamente un extremo final
sin hora explícita a `23:59:59`:

```php
DateRange::forMonth(2026, 1);              // date_to = 2026-01-31T23:59:59
DateRange::fromStrings('2026-01-01', '2026-01-31'); // igual
DateRange::fromStrings('2026-01-01T08:00:00', '2026-01-31T12:30:00'); // respeta la hora
```

Otras causas: el RFC consultado no tiene timbres en ese periodo, o
`invoice_type` no corresponde al tipo de comprobante.

### Lista vacía por `invoice_type` incorrecto

`invoice_type` es `xs:string` libre. Un valor fuera del catálogo (`I`, `E`, `T`,
`N`, `P`) **no produce error**: produce una lista vacía.

El bundle valida localmente con el enum `InvoiceType`:

```php
InvoiceType::fromKey('I');        // Ingreso
InvoiceType::fromKey('factura');  // Ingreso
InvoiceType::fromKey('X');        // ValidationException
```

### `get_xml` devuelve algo que no es un CFDI

Finkok conserva el XML de los **últimos 3 meses** (ticket #18950 del propio PAC).
Cuando el comprobante es más antiguo, devuelve la **ruta del archivo** en el
servidor en lugar de su contenido.

El bundle lo detecta y lo expone con `looksLikePath()` en lugar de entregarte una
cadena que guardarías como si fuera un CFDI:

```php
$xml = $this->utils->downloadXml($uuid);

if ($xml->looksLikePath()) {
    // Fuera de la ventana de retención: no es recuperable desde el PAC.
} elseif ($xml->isXmlDocument()) {
    file_put_contents($uuid.'.xml', (string) $xml->xml());
} elseif ($xml->isError()) {
    // "No se encontró el XML solicitado"
}
```

El bundle reintenta hasta `xml_max_attempts` veces (por omisión 3, con 200 ms entre
intentos) porque el contenido suele aparecer en el siguiente intento. Si tras todos
los intentos sigue siendo una ruta, se registra un `warning` con la sugerencia.

> **Nunca** uses `$result->xml()` sin comprobar `isXmlDocument()`: podrías guardar
> `/tmp/finkok/abc.xml` como contenido de un CFDI.

### El XML llega escapado o con entidades raras

`get_xml` declara `xml` como `xs:string`, así que el CFDI viaja escapado dentro del
envelope. El bundle lo decodifica una sola vez en `DomReader::xmlText()`.

Si necesitas el XML crudo byte a byte, usa `rawResponse()` y parsea tú mismo; pero
no decodifiques dos veces o corromperás las entidades legítimas del CFDI (`&amp;`,
`&quot;`).

### `TransportException` con HTTP 502/503

La página de error de un proxy **puede ser XML bien formado** (`<html>…</html>`),
de modo que `isXml()` no basta para descartarla: lo que la descarta es que no sea un
envelope SOAP.

Verifica:

1. La URL apunta a `/servicios/soap/utilities` (sin `.wsdl`).
2. No hay un proxy intermediando que reescriba la respuesta.
3. `TransportException::isRetryable()` es `true` para 429 y 5xx.

```php
catch (TransportException $e) {
    if ($e->isRetryable()) {
        // La política de reintentos del bundle ya lo intentó; decide si reintentar más tarde.
    }
    $e->getHttpStatusCode();
    $e->getResponseBody();
}
```

### `SoapFaultException` con `soap:Client`

La petición llegó pero no se pudo ejecutar como operación de negocio. Causas:

1. **Namespace equivocado** en el envelope (incidencia 738). El bundle usa
   `http://facturacion.finkok.com/utilities`.
2. Argumento con valor fuera de patrón — el caso típico es `zipcode` con algo que
   no cumple `[0-9]{5}`.
3. Operación inexistente: apuntar al WSDL de otro servicio (timbrado, cancelación)
   con el namespace de utilerías.

```php
catch (SoapFaultException $e) {
    $e->getFaultCode();    // "soap:Client"
    $e->getFaultString();
    $e->getEndpoint();
    $e->getOperation();
}
```

### Fechas con demasiados decimales

Los reportes devuelven `xs:dateTime` con precisión variable. PHP rechaza más de
seis decimales de fracción de segundo en el constructor de `DateTimeImmutable`.

El bundle trunca la fracción de forma transparente en `ModelDate`, preservando el
sufijo de zona (`Z`, `+00:00`):

```php
$item->dateTime('America/Mexico_City'); // ?DateTimeImmutable, nunca lanza
```

### «La aplicación no ha registrado un proveedor de credenciales»

El contenedor compila —deliberadamente, para que instalar el bundle no rompa
aplicaciones que aún no lo configuran— pero falla al usarse.

Registra un servicio que implemente `Estratos\FinkokUtilsBundle\Config\CredentialsProviderInterface`.
Consulta la sección «Configuración» del [README](../README.md).

### «El perfil … no define "taxpayerId"»

Los reportes se consultan **por RFC**. El perfil debe declarar `taxpayerId`, o cada
llamada debe pasarlo explícitamente:

```php
$this->utils->reportCredit(taxpayerId: 'EKU9003173C9');
```

### «El UUID … no tiene el formato de un Folio Fiscal»

La validación es local y evita una llamada inútil. El UUID debe tener la forma
`8-4-4-4-12` hexadecimal. El bundle normaliza a mayúsculas antes de validar, así que
puedes pasar minúsculas.

## Depurar el envelope enviado

Activa el log del payload para ver el XML exacto que se envía:

```yaml
finkok_utils:
    http:
        log_payloads: true   # solo en desarrollo
```

Para inspeccionar el envelope **sin abrir conexión**, construye el `SoapRequest`
directamente. Es lo que hacen las pruebas de contrato de este bundle y resulta útil
para comparar lo que genera el adaptador contra lo que documenta el WSDL:

```php
use Estratos\FinkokUtilsBundle\Config\Service;
use Estratos\FinkokUtilsBundle\Soap\SoapRequest;

$request = new SoapRequest(
    endpoint: Service::Utilities->defaultEndpoint($perfil->environment()),
    operation: 'report_uuid',
    namespace: Service::Utilities->namespace(),
    arguments: [
        'username' => $perfil->username(),
        'password' => $perfil->password(),
        'taxpayer_id' => 'EKU9003173C9',
        'date_from' => '2026-01-01T00:00:00',
        'date_to' => '2026-01-31T23:59:59',
    ],
);

echo $request->toXml();
```

También puedes sustituir el transporte por uno propio implementando
`SoapTransportInterface`, o inyectar un `MockHttpClient` en el servicio
`http_client` de la aplicación para capturar la petición real.

> `log_payloads` escribe RFC, montos y, en `get_xml`, el CFDI completo. **Nunca** lo
> actives en producción.

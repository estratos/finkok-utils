# Changelog

Todos los cambios relevantes de este bundle se documentan aquí.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el
proyecto se adhiere a [Versionado Semántico](https://semver.org/lang/es/).

## [1.0.0] - 2026-10-14

### Añadido

- Adaptador del Web Service de utilerías de Finkok (`utilities.wsdl`) para
  Symfony 7.4, sin dependencia de `ext-soap`.
- **Transporte SOAP desacoplado**: el envelope se construye a mano una sola vez y el
  envío es intercambiable mediante `RequestHandlerInterface`.
  - `SymfonyRequestHandler` sobre Symfony HttpClient (por omisión; garantiza timeout
    y política de reintentos).
  - `Psr18RequestHandler` sobre cualquier cliente **PSR-18** (Guzzle, php-http, …),
    con fábrica **PSR-17** obligatoria y configuración explícita
    (`finkok_utils.psr18`). PSR-18 no contempla timeouts, así que no se activa solo.
  - `Psr7Stream` propio, para no obligar a instalar una implementación PSR-7 concreta.
- Operaciones implementadas, con los argumentos y el orden declarados en el WSDL:
  - `datetime` → `UtilitiesServiceInterface::datetime()`
  - `get_xml` → `UtilitiesServiceInterface::downloadXml()`
  - `report_credit` → `UtilitiesServiceInterface::reportCredit()`
  - `report_total` → `UtilitiesServiceInterface::reportTotal()`
  - `report_uuid` → `UtilitiesServiceInterface::reportUuid()`
  - `get_idccp` → `UtilitiesServiceInterface::getIdCcp()`
  - `get_moneda_usd` → `UtilitiesServiceInterface::getMonedaUsd()`
- `IdCcp`: validación y generación del identificador del complemento Carta Porte 3.0
  (36 caracteres, prefijo `CCC` más cuatro grupos hexadecimales, forma RFC 4122), y
  `IdCcpResult::isValid()` / `normalized()` para verificar lo que devuelve el PAC.
- `ReportCreditResult::isUnlimited()`: distingue una cuenta **ilimitada** (respuesta
  sin el nodo `result`) de una cuenta de prepago sin movimientos en el periodo.
- DTOs de resultado: `DateTimeResult`, `DownloadXmlResult`, `IdCcpResult`,
  `MonedaResult`, `ReportCreditResult`, `ReportTotalResult`, `ReportUuidResult`.
- Value object `DateRange` con constructores para día, mes, año, rango de periodos
  y rango entre fechas. Extiende automáticamente un extremo final sin hora explícita
  a `23:59:59`, porque enviar `T00:00:00` excluye todos los comprobantes de ese día.
- Enum `InvoiceType` con el catálogo del SAT (`I`, `E`, `T`, `N`, `P`) y validación
  local: `invoice_type` es `xs:string` libre y un valor inválido produce una lista
  vacía en lugar de un error.
- Detección de la respuesta de `get_xml` que devuelve una **ruta de archivo** en
  lugar del CFDI (comprobantes fuera de la ventana de retención de 3 meses),
  expuesta mediante `DownloadXmlResult::looksLikePath()`, con reintentos
  configurables.
- Lectura tolerante de `xs:dateTime` con más de seis decimales de fracción de
  segundo, que `DateTimeImmutable` rechaza.
- Perfiles de credenciales multi-RFC (`CredentialsProvider`) y resolución de
  endpoints con precedencia perfil → configuración → valor por defecto.
- Integración con Symfony: extensión bajo la clave `finkok_utils`, compiler pass de
  cableado y servicios públicos por interfaz para permitir decoración.
- Jerarquía de excepciones que distingue validación local, configuración,
  transporte, SOAP Fault y respuesta inesperada.
- Suite de 153 pruebas apoyada en respuestas SOAP reales de Finkok, más pruebas de
  contrato contra el ambiente de demostración (grupo `live`).

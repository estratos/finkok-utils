# Contrato de `utilities.wsdl`

Documento de referencia del Web Service de utilerías de Finkok, extraído del WSDL
oficial (`https://demo-facturacion.finkok.com/servicios/soap/utilities.wsdl`).

## Datos generales

| Dato | Valor |
|---|---|
| `targetNamespace` | `http://facturacion.finkok.com/utilities` |
| Namespace de tipos compartidos | `apps.services.soap.core.views` |
| Estilo | `document` / `literal` |
| SOAP | 1.1 |
| Nombre del servicio | `UtilitiesSOAP` |
| Puerto | `Application` |
| Endpoint demo | `https://demo-facturacion.finkok.com/servicios/soap/utilities` |
| Endpoint productivo | `https://facturacion.finkok.com/servicios/soap/utilities` |

`soapAction` es igual al nombre de la operación en todas ellas.

El WSDL declara **dos** `xs:schema` con `elementFormDefault="qualified"`:

1. `apps.services.soap.core.views` — tipos de resultado.
2. `http://facturacion.finkok.com/utilities` — operaciones y sus tipos de entrada.

Cada elemento debe emitirse en el namespace donde fue declarado. En particular,
`<operacion>` y `<operacion>Result` viven en el namespace **del servicio**, no en
el de tipos compartidos (aunque los hijos complejos de ese resultado sí).

## Operaciones

### `datetime`

Devuelve la fecha y hora de los servidores de Finkok.

| Argumento | Tipo | Ocurrencia | Notas |
|---|---|---|---|
| `username` | `xs:string` | `minOccurs="0"` | |
| `password` | `xs:string` | `minOccurs="0"` | |
| `zipcode` | `tns:datetime_zipcodeType` | `minOccurs="0"` | Patrón `[0-9]{5}` |

Resultado: `s0:DateResult`

| Campo | Tipo |
|---|---|
| `error` | `xs:string` |
| `datetime` | `xs:string` |

Ejemplo de respuesta:

```xml
<tns:datetimeResponse xmlns:tns="http://facturacion.finkok.com/utilities">
  <tns:datetimeResult xmlns:s0="apps.services.soap.core.views">
    <s0:error xsi:nil="true"/>
    <s0:datetime>2026-10-14T18:25:31</s0:datetime>
  </tns:datetimeResult>
</tns:datetimeResponse>
```

> `zipcode` es el único argumento del WSDL con restricción de patrón. Enviar un
> valor que no cumpla `[0-9]{5}` provoca un SOAP Fault de validación de esquema, así
> que el bundle lo valida antes de enviar.

### `get_xml`

Devuelve el XML de un CFDI timbrado.

| Argumento | Tipo | Ocurrencia |
|---|---|---|
| `username` | `xs:string` | `minOccurs="0"` |
| `password` | `xs:string` | `minOccurs="0"` |
| `uuid` | `xs:string` | `minOccurs="0"` |
| `taxpayer_id` | `xs:string` | `minOccurs="0"` |
| `invoice_type` | `xs:string` | `minOccurs="0"` |

Resultado: `s0:XMLResult`

| Campo | Tipo |
|---|---|
| `xml` | `xs:string` |
| `error` | `xs:string` |

> **El XML viaja como `xs:string`, no como `xs:base64Binary`.** Eso significa que
> llega escapado dentro del envelope (`&lt;?xml …`) y que debe decodificarse
> exactamente una vez. El bundle lo hace en `DomReader::xmlText()`.
>
> **Ventana de retención:** Finkok sólo conserva 3 meses. Fuera de ese plazo
> devuelve la *ruta del archivo* en lugar del contenido. Ver
> `DownloadXmlResult::looksLikePath()`.

### `report_credit`

Créditos añadidos a la cuenta.

| Argumento | Tipo | Ocurrencia |
|---|---|---|
| `username` | `xs:string` | **obligatorio** |
| `password` | `xs:string` | **obligatorio** |
| `taxpayer_id` | `xs:string` | **obligatorio** |

Resultado: `s0:ReportTotalCreditResult`

```
report_creditResult
└── result
    ├── ReportTotalCredit        (0..∞)  s0:ReportTotalCredit
    │   ├── taxpayer_id  xs:string
    │   ├── credit       xs:integer
    │   └── date         xs:dateTime
    └── error  xs:string
```

> Esta operación **no** acepta rango de fechas y sus tres argumentos son
> obligatorios en el WSDL (a diferencia de `datetime` y `get_xml`).

### `report_total`

Comprobantes timbrados por RFC dentro de un rango.

| Argumento | Tipo | Ocurrencia |
|---|---|---|
| `username` | `xs:string` | **obligatorio** |
| `password` | `xs:string` | **obligatorio** |
| `taxpayer_id` | `xs:string` | **obligatorio** |
| `date_from` | `xs:dateTime` | **obligatorio** |
| `date_to` | `xs:dateTime` | **obligatorio** |
| `invoice_type` | `xs:string` | `minOccurs="0"` |

Resultado: `s0:ReportTotalResult`

```
report_totalResult
└── result
    ├── ReportTotal        (0..∞)  s0:ReportTotal
    │   ├── total        xs:integer
    │   └── taxpayer_id  xs:string
    └── error  xs:string
```

> Atención al **orden**: `invoice_type` se declara **después** de `date_from` y
> `date_to`, no junto a `taxpayer_id`. Un cliente que envíe los argumentos en otro
> orden puede recibir un Fault de validación.
>
> `total` es un **conteo de comprobantes** (`xs:integer`), no un importe.

### `report_uuid`

Folios Fiscales emitidos por un RFC dentro de un rango.

| Argumento | Tipo | Ocurrencia |
|---|---|---|
| `username` | `xs:string` | **obligatorio** |
| `password` | `xs:string` | **obligatorio** |
| `taxpayer_id` | `xs:string` | **obligatorio** |
| `date_from` | `xs:dateTime` | **obligatorio** |
| `date_to` | `xs:dateTime` | **obligatorio** |
| `invoice_type` | `xs:string` | `minOccurs="0"` |

Resultado: `s0:ReportUUIDResult`

```
report_uuidResult
├── invoices
│   └── ReportUUID      (0..∞)  s0:ReportUUID
│       ├── uuid          xs:string
│       ├── taxpayer_id   xs:string
│       ├── rtaxpayer_id  xs:string
│       ├── date          xs:dateTime
│       └── total         xs:float
└── error  xs:string
```

> El contenedor se llama **`invoices`**, no `result` — es la única diferencia de
> forma con `report_total` y `report_credit`.
>
> Aunque el WSDL declara los cinco campos de `ReportUUID`, las respuestas reales
> más comunes sólo incluyen `date` y `uuid`. El bundle expone los demás como
> opcionales.

### `get_idccp`

Identificador del complemento Carta Porte.

| Argumento | Tipo | Ocurrencia |
|---|---|---|
| `username` | `xs:string` | `minOccurs="0"` |
| `password` | `xs:string` | `minOccurs="0"` |

Resultado: `s0:IdCCPResult`

| Campo | Tipo |
|---|---|
| `idccp` | `xs:string` |
| `error` | `xs:string` |

> El WSDL describe esta operación con el texto «This class returns the system
> date», que es una **documentación copiada por error** de `datetime`. La firma
> real (sólo credenciales) y el tipo de resultado (`IdCCPResult`) indican que
> devuelve el `IdCCP` del complemento Carta Porte.

### `get_moneda_usd`

Tipo de cambio del dólar.

| Argumento | Tipo | Ocurrencia |
|---|---|---|
| `username` | `xs:string` | `minOccurs="0"` |
| `password` | `xs:string` | `minOccurs="0"` |
| `date` | `xs:string` | `minOccurs="0"` |

Resultado: `s0:MonedaResult`

| Campo | Tipo |
|---|---|
| `moneda` | `xs:string` |
| `error` | `xs:string` |

> `moneda` se declara `xs:string`, no numérico; el bundle expone además
> `MonedaResult::rate(): ?float` para el valor interpretado.

## Operaciones declaradas y no implementadas

El WSDL también declara `add_token`, `reset_token` y `update_token` (gestión de
tokens de usuario). No se implementan porque no forman parte de las utilerías
solicitadas y porque modifican el estado de la cuenta, a diferencia del resto de
las operaciones de este WSDL, que son de solo lectura.

## Tipos del esquema compartido

| Tipo | Campos |
|---|---|
| `DateResult` | `error`, `datetime` |
| `XMLResult` | `xml`, `error` |
| `IdCCPResult` | `idccp`, `error` |
| `MonedaResult` | `moneda`, `error` |
| `ResponseToken` | `username`, `status`, `name`, `success`, `token`, `message` |
| `ReportUUID` | `uuid`, `taxpayer_id`, `rtaxpayer_id`, `date`, `total` |
| `ReportUUIDArray` | `ReportUUID` (0..∞) |
| `ReportUUIDResult` | `invoices`, `error` |
| `ReportTotal` | `total`, `taxpayer_id` |
| `ReportTotalArray` | `ReportTotal` (0..∞) |
| `ReportTotalResult` | `result`, `error` |
| `ReportTotalCredit` | `taxpayer_id`, `credit`, `date` |
| `ReportTotalCreditArray` | `ReportTotalCredit` (0..∞) |
| `ReportTotalCreditResult` | `result`, `error` |

<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Resultado de `get_xml`: el CFDI timbrado que corresponde a un UUID.
 *
 * El `xml` viaja como `xs:string` dentro del envelope (no como `base64Binary`), de
 * modo que llega escapado; {@see \Estratos\FinkokUtilsBundle\Xml\DomReader::xmlText()}
 * ya lo decodifica una sola vez.
 *
 * **Advertencia operativa:** Finkok solo conserva el XML de los **últimos 3
 * meses** y, cuando el comprobante es antiguo o el UUID no existe, responde la
 * *ruta* del archivo en lugar de su contenido (por ejemplo `/tmp/abc.xml`). Este
 * adaptador detecta ese caso y lo expone con {@see looksLikePath()} en lugar de
 * devolver una cadena que el consumidor confundiría con un CFDI.
 */
final class DownloadXmlResult extends UtilitiesResult
{
    public function __construct(
        private readonly ?string $xml = null,
        ?string $error = null,
        ?string $rawResponse = null,
    ) {
        parent::__construct($error, $rawResponse);
    }

    /**
     * Contenido del CFDI, o `null`.
     */
    public function xml(): ?string
    {
        return $this->xml;
    }

    public function hasXml(): bool
    {
        return null !== $this->xml && '' !== trim($this->xml);
    }

    /**
     * `true` cuando lo que llegó **no** es un CFDI sino la ruta de un archivo del
     * servidor de Finkok.
     *
     * Ocurre con comprobantes fuera de la ventana de 3 meses. El consumidor debe
     * tratar este caso como «no disponible» y volver a solicitar el XML más tarde
     * o marcarlo como irrecuperable, nunca guardarlo como si fuera un CFDI.
     */
    public function looksLikePath(): bool
    {
        if (!$this->hasXml()) {
            return false;
        }

        $xml = (string) $this->xml;

        return str_ends_with(strtolower($xml), '.xml') && !str_contains($xml, '<');
    }

    /**
     * `true` cuando el contenido es un documento XML utilizable.
     */
    public function isXmlDocument(): bool
    {
        return $this->hasXml() && str_contains((string) $this->xml, '<');
    }

    /**
     * CFDI como `DOMDocument`, o `null` si no hay un XML interpretable.
     */
    public function toDocument(): ?\DOMDocument
    {
        if (!$this->isXmlDocument()) {
            return null;
        }

        $document = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $loaded = @$document->loadXML((string) $this->xml, LIBXML_NONET | LIBXML_PARSEHUGE);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $loaded ? $document : null;
    }

    public function isSuccess(): bool
    {
        return !$this->isError() && $this->isXmlDocument();
    }
}

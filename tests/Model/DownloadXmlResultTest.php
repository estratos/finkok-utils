<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Model;

use Estratos\FinkokUtilsBundle\Model\DownloadXmlResult;
use PHPUnit\Framework\TestCase;

final class DownloadXmlResultTest extends TestCase
{
    private const COMPROBANTE = '<?xml version="1.0" encoding="UTF-8"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4"/>';

    public function testDetectsARealXmlDocument(): void
    {
        $result = new DownloadXmlResult(self::COMPROBANTE);

        self::assertTrue($result->isXmlDocument());
        self::assertFalse($result->looksLikePath());
        self::assertTrue($result->isSuccess());
        self::assertInstanceOf(\DOMDocument::class, $result->toDocument());
        self::assertTrue($result->hasXml());
    }

    /**
     * Finkok responde la ruta del archivo cuando el comprobante está fuera de la
     * ventana de 3 meses (ticket #18950). Debe exponerse como tal para que el
     * consumidor no lo guarde como si fuera un CFDI.
     */
    public function testDetectsFilePathInsteadOfContent(): void
    {
        $result = new DownloadXmlResult('/tmp/finkok/abc.xml');

        self::assertTrue($result->looksLikePath());
        self::assertFalse($result->isXmlDocument());
        self::assertFalse($result->isSuccess());
        self::assertNull($result->toDocument());
    }

    public function testEmptyValueIsNeitherXmlNorPath(): void
    {
        $result = new DownloadXmlResult();

        self::assertFalse($result->hasXml());
        self::assertFalse($result->looksLikePath());
        self::assertFalse($result->isXmlDocument());
        self::assertFalse($result->isSuccess());
        self::assertNull($result->xml());
    }

    public function testErrorTakesPrecedenceOverSuccess(): void
    {
        $result = new DownloadXmlResult(self::COMPROBANTE, 'No se encontró el XML');

        self::assertTrue($result->isError());
        self::assertSame('No se encontró el XML', $result->error());
        self::assertFalse($result->isSuccess());
    }
}

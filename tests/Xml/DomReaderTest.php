<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Xml;

use Estratos\FinkokUtilsBundle\Exception\UnexpectedResponseException;
use Estratos\FinkokUtilsBundle\Xml\DomReader;
use PHPUnit\Framework\TestCase;

final class DomReaderTest extends TestCase
{
    private function document(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));

        return $document;
    }

    public function testReadsElementsByLocalNameRegardlessOfPrefix(): void
    {
        $document = $this->document(
            '<root xmlns:a="urn:uno"><a:ReportUUID><a:uuid>abc</a:uuid></a:ReportUUID></root>',
        );

        $root = $document->documentElement;
        self::assertInstanceOf(\DOMElement::class, $root);

        $element = DomReader::firstElement($root, 'ReportUUID');
        self::assertInstanceOf(\DOMElement::class, $element);
        self::assertSame('urn:uno', $element->namespaceURI);
        self::assertSame('abc', DomReader::textOf($element, 'uuid'));
    }

    /**
     * El WSDL distingue `ReportUUID` de `ReportUUIDArray`; la comparación debe ser
     * exacta y sensible a mayúsculas.
     */
    public function testLocalNameComparisonIsCaseSensitive(): void
    {
        $document = $this->document(
            '<root><ReportUUID>uno</ReportUUID><reportuuid>dos</reportuuid></root>',
        );

        $root = $document->documentElement;
        self::assertInstanceOf(\DOMElement::class, $root);

        // `textOf` devuelve el valor del primer hijo directo cuyo nombre local
        // coincide **exactamente**, así que `reportuuid` no coincide con `ReportUUID`.
        $exact = DomReader::firstElement($root, 'ReportUUID');
        self::assertInstanceOf(\DOMElement::class, $exact);
        self::assertSame('uno', DomReader::text($exact));

        $lower = DomReader::firstElement($root, 'reportuuid');
        self::assertInstanceOf(\DOMElement::class, $lower);
        self::assertSame('dos', DomReader::text($lower));

        // Ninguno de los dos nombres resuelve al otro.
        self::assertNull(DomReader::firstElement($root, 'REPORTUUID'));
        self::assertNull(DomReader::firstElement($root, 'Reportuuid'));
    }

    public function testReadsRepeatedChildrenInOrder(): void
    {
        $document = $this->document(
            '<root><ReportUUID>1</ReportUUID><ReportUUID>2</ReportUUID><ReportUUID>3</ReportUUID></root>',
        );

        $root = $document->documentElement;
        self::assertInstanceOf(\DOMElement::class, $root);

        $items = DomReader::elements($root, 'ReportUUID');
        self::assertCount(3, $items);
        self::assertSame(['1', '2', '3'], array_map(
            static fn (\DOMElement $e): ?string => DomReader::text($e),
            $items,
        ));
    }

    public function testTextNormalizesCarriageReturnsAndTrims(): void
    {
        $document = $this->document("<root><v>  linea1\r\nlinea2  </v></root>");
        $root = $document->documentElement;
        self::assertInstanceOf(\DOMElement::class, $root);

        self::assertSame("linea1\nlinea2", DomReader::textOf($root, 'v'));
    }

    public function testTextReturnsNullForEmptyElement(): void
    {
        $document = $this->document('<root><v>   </v><w/></root>');
        $root = $document->documentElement;
        self::assertInstanceOf(\DOMElement::class, $root);

        self::assertNull(DomReader::textOf($root, 'v'));
        self::assertNull(DomReader::textOf($root, 'w'));
        self::assertNull(DomReader::textOf($root, 'inexistente'));
    }

    /**
     * `get_xml` entrega el CFDI escapado dentro del envelope.
     */
    public function testXmlTextDecodesDoublyEscapedDocuments(): void
    {
        $document = $this->document(
            '<root><xml>&lt;?xml version="1.0"?&gt;&lt;cfdi:Comprobante/&gt;</xml></root>',
        );
        $root = $document->documentElement;
        self::assertInstanceOf(\DOMElement::class, $root);

        $xml = DomReader::firstElement($root, 'xml');
        self::assertInstanceOf(\DOMElement::class, $xml);

        $decoded = DomReader::xmlText($xml);
        self::assertNotNull($decoded);
        self::assertStringStartsWith('<?xml', $decoded);
        self::assertStringContainsString('<cfdi:Comprobante/>', $decoded);
    }

    public function testXmlTextPreservesLegitimateEntities(): void
    {
        $document = $this->document('<root><xml>Empresa &amp; Cia</xml></root>');
        $root = $document->documentElement;
        self::assertInstanceOf(\DOMElement::class, $root);

        $xml = DomReader::firstElement($root, 'xml');
        self::assertInstanceOf(\DOMElement::class, $xml);

        self::assertSame('Empresa & Cia', DomReader::xmlText($xml));
    }

    public function testDescendantsSearchTheWholeSubtree(): void
    {
        $document = $this->document('<root><a><b><c>hondo</c></b></a></root>');
        $root = $document->documentElement;
        self::assertInstanceOf(\DOMElement::class, $root);

        self::assertNull(DomReader::firstElement($root, 'c'));

        $found = DomReader::descendant($root, 'c');
        self::assertInstanceOf(\DOMElement::class, $found);
        self::assertSame('hondo', DomReader::text($found));
        self::assertCount(1, DomReader::descendants($root, 'c'));
    }

    public function testConvertsScalarTypes(): void
    {
        self::assertSame(123, DomReader::toInt('123'));
        self::assertNull(DomReader::toInt('N/A'));
        self::assertNull(DomReader::toInt(null));

        self::assertSame(17.5, DomReader::toFloat('17.5'));
        self::assertNull(DomReader::toFloat(''));

        self::assertTrue(DomReader::toBoolean('true'));
        self::assertTrue(DomReader::toBoolean('1'));
        self::assertFalse(DomReader::toBoolean('false'));
        self::assertNull(DomReader::toBoolean(null));
    }

    public function testLoadDocumentThrowsWithContextOnInvalidXml(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessageMatches('/la respuesta de get_xml/');

        DomReader::loadDocument('<roto>', 'la respuesta de get_xml');
    }

    public function testLoadDocumentRejectsEmptyInput(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        DomReader::loadDocument('   ', 'contexto');
    }

    public function testLoadDocumentSucceedsForValidXml(): void
    {
        $document = DomReader::loadDocument('<ok/>', 'contexto');

        self::assertSame('ok', $document->documentElement?->localName);
    }
}

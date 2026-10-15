<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Value;

use Estratos\FinkokUtilsBundle\Soap\SoapValueInterface;
use Estratos\FinkokUtilsBundle\Soap\SoapWriter;

/**
 * Valor escalar serializado como texto.
 */
final class TextValue implements SoapValueInterface
{
    private readonly string $text;

    public function __construct(string|int|float|bool $value)
    {
        $this->text = match (true) {
            \is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    public function text(): string
    {
        return $this->text;
    }

    public function write(SoapWriter $writer, \DOMElement $parent, string $name, string $namespace): void
    {
        $writer->textElement($parent, $name, $namespace, $this->text);
    }
}

<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Soap\Psr18;

use Psr\Http\Message\StreamInterface;

/**
 * Implementación mínima de `Psr\Http\Message\StreamInterface` sobre un recurso PHP.
 *
 * Existe para que el bundle pueda enviar peticiones PSR-18 sin obligar a la
 * aplicación a instalar una implementación PSR-7 concreta (nyholm/psr7, guzzle,
 * laminas-diactoros…). `RequestFactoryInterface` (PSR-17) no ofrece forma de crear
 * flujos, así que el cuerpo del envelope se envuelve aquí.
 *
 * Solo se implementa lo que el transporte necesita —lectura y escritura básicas—
 * pero todos los métodos del contrato quedan operativos.
 *
 * @internal
 */
final class Psr7Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    private ?int $size = null;

    /**
     * @param resource $resource
     */
    public function __construct($resource)
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException('Se esperaba un recurso de flujo válido.');
        }

        $this->resource = $resource;
    }

    public function __toString(): string
    {
        if (null === $this->resource) {
            return '';
        }

        if ($this->isSeekable()) {
            $this->rewind();
        }

        return $this->getContents();
    }

    public function close(): void
    {
        if (null !== $this->resource) {
            fclose($this->resource);
        }

        $this->resource = null;
        $this->size = null;
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        $this->size = null;

        return $resource;
    }

    public function getSize(): ?int
    {
        if (null === $this->resource) {
            return null;
        }

        if (null !== $this->size) {
            return $this->size;
        }

        $stats = fstat($this->resource);

        return $this->size = \is_array($stats) ? ($stats['size'] ?? null) : null;
    }

    public function tell(): int
    {
        $this->assertAttached();

        $position = ftell($this->resource);

        if (false === $position) {
            throw new \RuntimeException('No fue posible obtener la posición del flujo.');
        }

        return $position;
    }

    public function eof(): bool
    {
        return null === $this->resource || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        if (null === $this->resource) {
            return false;
        }

        return (bool) ($this->metadata()['seekable'] ?? false);
    }

    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        $this->assertAttached();

        if (!$this->isSeekable()) {
            throw new \RuntimeException('El flujo no permite posicionamiento.');
        }

        if (0 !== fseek($this->resource, $offset, $whence)) {
            throw new \RuntimeException('No fue posible posicionar el flujo.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        if (null === $this->resource) {
            return false;
        }

        $mode = (string) ($this->metadata()['mode'] ?? '');

        return strpbrk($mode, 'waxc+') !== false;
    }

    public function write(string $string): int
    {
        $this->assertAttached();

        if (!$this->isWritable()) {
            throw new \RuntimeException('El flujo no permite escritura.');
        }

        $written = fwrite($this->resource, $string);

        if (false === $written) {
            throw new \RuntimeException('No fue posible escribir en el flujo.');
        }

        $this->size = null;

        return $written;
    }

    public function isReadable(): bool
    {
        if (null === $this->resource) {
            return false;
        }

        $mode = (string) ($this->metadata()['mode'] ?? '');

        return str_contains($mode, 'r') || str_contains($mode, '+');
    }

    public function read(int $length): string
    {
        $this->assertAttached();

        if ($length < 0) {
            throw new \RuntimeException('La longitud a leer no puede ser negativa.');
        }

        if (0 === $length) {
            return '';
        }

        if (!$this->isReadable()) {
            throw new \RuntimeException('El flujo no permite lectura.');
        }

        $data = fread($this->resource, $length);

        if (false === $data) {
            throw new \RuntimeException('No fue posible leer del flujo.');
        }

        return $data;
    }

    public function getContents(): string
    {
        $this->assertAttached();

        $contents = stream_get_contents($this->resource);

        if (false === $contents) {
            throw new \RuntimeException('No fue posible leer el contenido del flujo.');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null)
    {
        if (null === $this->resource) {
            return null === $key ? [] : null;
        }

        $metadata = stream_get_meta_data($this->resource);

        if (null === $key) {
            return $metadata;
        }

        return $metadata[$key] ?? null;
    }

    private function assertAttached(): void
    {
        if (null === $this->resource) {
            throw new \RuntimeException('El flujo ya fue cerrado o liberado.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(): array
    {
        if (null === $this->resource) {
            return [];
        }

        return stream_get_meta_data($this->resource);
    }
}

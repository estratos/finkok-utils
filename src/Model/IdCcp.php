<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Model;

/**
 * Identificador del complemento Carta Porte (`ccp:IdCCP`).
 *
 * En la versión 3.0 del complemento Carta Porte el atributo `IdCCP` es
 * obligatorio, mide **36 caracteres** y sigue el estándar RFC 4122 para UUID, con
 * la particularidad de que los tres primeros caracteres son siempre `CCC`:
 *
 * ```text
 * CCC47D35-5855-4E23-8A52-3AB33221AE09
 * └┬┘└─┬─┘ └─┬─┘ └─┬─┘ └────┬─────┘
 *  │   │     │     │        └── 12 hexadecimales
 *  │   │     │     └─────────── 4 hexadecimales
 *  │   │     └───────────────── 4 hexadecimales
 *  │   └─────────────────────── 5 hexadecimales
 *  └─────────────────────────── prefijo fijo "CCC"
 * ```
 *
 * Patrón (según la documentación de Finkok):
 *
 * ```text
 * [C]{3}[a-f0-9A-F]{5}-[a-f0-9A-F]{4}-[a-f0-9A-F]{4}-[a-f0-9A-F]{4}-[a-f0-9A-F]{12}
 * ```
 *
 * Los cuatro grupos hexadecimales posteriores al prefijo se generan localmente: el
 * contribuyente **proporciona** el identificador al CFDI, Finkok sólo lo expide y
 * lo devuelve. Usa {@see generate()} si necesitas uno, o {@see isValid()} para
 * verificar el que hayas recibido.
 *
 * El valor se compara sin distinguir mayúsculas de minúsculas, porque el patrón
 * documentado admite ambas formas aunque el ejemplo oficial venga en mayúsculas.
 */
final class IdCcp
{
    /** Longitud exacta exigida por el SAT: 36 caracteres. */
    public const LENGTH = 36;

    /** Prefijo fijo del identificador. */
    public const PREFIX = 'CCC';

    /**
     * Grupo hexadecimal de 4 caracteres, repetido tres veces, más uno de 12.
     */
    private const PATTERN = '/^[C]{3}[a-f0-9]{5}(-[a-f0-9]{4}){3}-[a-f0-9]{12}$/i';

    private function __construct()
    {
    }

    /**
     * `true` si el valor cumple el formato del `IdCCP`.
     *
     * Comprueba longitud, prefijo `CCC` y los cuatro grupos hexadecimales. Devuelve
     * `false` para `null` y cadenas vacías en lugar de lanzar: se usa tanto para
     * validar entrada del usuario como para verificar respuestas del PAC.
     */
    public static function isValid(?string $value): bool
    {
        if (null === $value) {
            return false;
        }

        $value = trim($value);

        if (self::LENGTH !== \strlen($value)) {
            return false;
        }

        return 1 === preg_match(self::PATTERN, $value);
    }

    /**
     * Normaliza un identificador a su forma canónica en mayúsculas.
     *
     * @throws \Estratos\FinkokUtilsBundle\Exception\ValidationException si el valor no cumple el formato
     */
    public static function normalize(string $value): string
    {
        $value = strtoupper(trim($value));

        if (!self::isValid($value)) {
            throw new \Estratos\FinkokUtilsBundle\Exception\ValidationException(sprintf(
                'El IdCCP "%s" no cumple el formato exigido por el complemento Carta Porte 3.0: '
                .'36 caracteres con prefijo "CCC" y cuatro grupos hexadecimales '
                .'(CCCXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX).',
                $value,
            ));
        }

        return $value;
    }

    /**
     * Genera un `IdCCP` nuevo con la parte hexadecimal aleatoria.
     *
     * Se respeta la forma RFC 4122 (versión 4 y variante 1) en los bits
     * correspondientes a los dos primeros grupos hexadecimales de 4 caracteres, de
     * modo que el resultado sea un UUID válido además de cumplir el patrón de
     * Finkok. No deriva del UUID del CFDI: el `IdCCP` se acuña **antes** de timbrar.
     */
    public static function generate(): string
    {
        $bytes = random_bytes(16);

        // Versión 4 (bits 12-15 del byte 6).
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        // Variante 1 (bits 14-15 del byte 8).
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        $hex = strtoupper(bin2hex($bytes));

        return sprintf(
            '%s%s-%s-%s-%s-%s',
            self::PREFIX,
            substr($hex, 0, 5),
            substr($hex, 5, 4),
            substr($hex, 9, 4),
            substr($hex, 13, 4),
            substr($hex, 17, 12),
        );
    }
}

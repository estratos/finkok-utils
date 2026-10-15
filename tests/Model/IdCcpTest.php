<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Model;

use Estratos\FinkokUtilsBundle\Exception\ValidationException;
use Estratos\FinkokUtilsBundle\Hydrator\ScalarResultHydrator;
use Estratos\FinkokUtilsBundle\Model\IdCcp;
use Estratos\FinkokUtilsBundle\Tests\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * El `IdCCP` del complemento Carta Porte 3.0 mide 36 caracteres y empieza con
 * `CCC`; el resto son cuatro grupos hexadecimales.
 */
final class IdCcpTest extends TestCase
{
    /** Ejemplo oficial de la documentación de Finkok. */
    private const OFFICIAL = 'CCC47D35-5855-4E23-8A52-3AB33221AE09';

    public function testAcceptsTheOfficialExample(): void
    {
        self::assertTrue(IdCcp::isValid(self::OFFICIAL));
        self::assertSame(36, \strlen(self::OFFICIAL));
        self::assertSame(self::OFFICIAL, IdCcp::normalize(self::OFFICIAL));
    }

    /**
     * El patrón documentado admite `a-f` y `A-F`, aunque el ejemplo venga en
     * mayúsculas.
     */
    public function testIsCaseInsensitive(): void
    {
        self::assertTrue(IdCcp::isValid(strtolower(self::OFFICIAL)));
        self::assertSame(self::OFFICIAL, IdCcp::normalize(strtolower(self::OFFICIAL)));
    }

    public function testIgnoresSurroundingWhitespace(): void
    {
        self::assertTrue(IdCcp::isValid('  '.self::OFFICIAL."\n"));
        self::assertSame(self::OFFICIAL, IdCcp::normalize(' '.self::OFFICIAL.' '));
    }

    /**
     * @return list<array{string|null, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'null' => [null, 'valor nulo'],
            'vacío' => ['', 'cadena vacía'],
            'sólo espacios' => ['   ', 'espacios'],
            // El prefijo CCP (2 C) corresponde a la nomenclatura del atributo, no
            // al valor, que exige tres C.
            'prefijo CCP' => ['CCP47D35-5855-4E23-8A52-3AB33221AE09', 'dos C'],
            'prefijo CCCP' => ['CCCP47D3-5855-4E23-8A52-3AB33221AE09', 'cuatro caracteres antes del guion'],
            'UUID normal' => ['47D35585-554E-238A-523A-B33221AE09AA', 'sin prefijo CCC'],
            'demasiado corto' => ['CCC47D35-5855-4E23-8A52-3AB33221AE0', '35 caracteres'],
            'demasiado largo' => ['CCC47D35-5855-4E23-8A52-3AB33221AE099', '37 caracteres'],
            'no hexadecimal' => ['CCC47G35-5855-4E23-8A52-3AB33221AE09', 'G no es hexadecimal'],
            'guiones mal puestos' => ['CCC47D355-855-4E23-8A52-3AB33221AE09', 'grupos de tamaño incorrecto'],
            'sin guiones' => ['CCC47D3558554E23A523AB33221AE09AAAA', 'sin separadores'],
            'texto libre' => ['no-es-un-idccp', 'texto arbitrario'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsMalformedValues(?string $value, string $reason): void
    {
        self::assertFalse(IdCcp::isValid($value), sprintf('Debería rechazar: %s', $reason));
    }

    #[DataProvider('invalidValues')]
    public function testNormalizeThrowsOnMalformedValues(?string $value, string $reason): void
    {
        try {
            IdCcp::normalize((string) $value);
            self::fail(sprintf('Se esperaba ValidationException para: %s', $reason));
        } catch (ValidationException $exception) {
            self::assertStringContainsString('Carta Porte 3.0', $exception->getMessage());
        }
    }

    public function testGenerateProducesAValidIdentifier(): void
    {
        $idccp = IdCcp::generate();

        self::assertTrue(IdCcp::isValid($idccp), sprintf('Generado inválido: %s', $idccp));
        self::assertSame(36, \strlen($idccp));
        self::assertStringStartsWith('CCC', $idccp);
        self::assertSame(strtoupper($idccp), $idccp, 'El generado debe venir en mayúsculas.');
    }

    /**
     * Se respeta la forma RFC 4122 (versión 4 y variante 1) en los grupos
     * hexadecimales, de modo que el identificador sea además un UUID bien formado.
     */
    public function testGenerateRespectsRfc4122VersionAndVariant(): void
    {
        // El prefijo `CCC` (3) desplaza la rejilla del UUID tres posiciones: los
        // bytes 6 y 8 del UUID caen en los índices 15 y 19 de la forma compacta.
        for ($i = 0; $i < 200; ++$i) {
            $idccp = IdCcp::generate();
            $compact = str_replace('-', '', $idccp);

            self::assertSame('4', $compact[15], sprintf('Versión incorrecta en %s', $idccp));
            self::assertContains(
                $compact[19],
                ['8', '9', 'A', 'B'],
                sprintf('Variante incorrecta en %s', $idccp),
            );
        }
    }

    public function testGenerateProducesDistinctValues(): void
    {
        $generated = [];

        for ($i = 0; $i < 200; ++$i) {
            $generated[] = IdCcp::generate();
        }

        self::assertCount(200, array_unique($generated));
    }

    public function testResultExposesFormatValidation(): void
    {
        $result = ScalarResultHydrator::idCcp(Fixtures::resultElement('get-idccp-success.xml', 'get_idccp'));

        self::assertSame(self::OFFICIAL, $result->idccp());
        self::assertTrue($result->isValid());
        self::assertSame(self::OFFICIAL, $result->normalized());
    }

    public function testResultNormalizesLowercaseValues(): void
    {
        $result = ScalarResultHydrator::idCcp(Fixtures::resultElement('get-idccp-lowercase.xml', 'get_idccp'));

        self::assertTrue($result->isValid());
        self::assertSame(self::OFFICIAL, $result->normalized());
    }

    /**
     * Un valor presente pero mal formado debe poder detectarse antes de escribirlo
     * en el CFDI: el SAT rechazaría el comprobante.
     */
    public function testResultFlagsMalformedValues(): void
    {
        $result = ScalarResultHydrator::idCcp(
            Fixtures::resultElement('get-idccp-missing-prefix.xml', 'get_idccp'),
        );

        self::assertTrue($result->hasIdccp());
        self::assertTrue($result->isSuccess(), 'El PAC respondió sin error.');
        self::assertFalse($result->isValid(), 'Pero el valor no cumple el formato exigido.');
        self::assertNull($result->normalized());
    }

    public function testResultWithoutValueIsNotValid(): void
    {
        $result = ScalarResultHydrator::idCcp(null);

        self::assertFalse($result->hasIdccp());
        self::assertFalse($result->isValid());
        self::assertNull($result->normalized());
    }
}

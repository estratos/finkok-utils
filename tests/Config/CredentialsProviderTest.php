<?php

declare(strict_types=1);

namespace Estratos\FinkokUtilsBundle\Tests\Config;

use Estratos\FinkokUtilsBundle\Config\Credentials;
use Estratos\FinkokUtilsBundle\Config\CredentialsProvider;
use Estratos\FinkokUtilsBundle\Exception\ProfileNotFoundException;
use PHPUnit\Framework\TestCase;

final class CredentialsProviderTest extends TestCase
{
    public function testFirstProfileBecomesTheDefault(): void
    {
        $provider = new CredentialsProvider([
            'matriz' => new Credentials('matriz', 'u1', 'p1'),
            'sucursal' => new Credentials('sucursal', 'u2', 'p2'),
        ]);

        self::assertSame(['matriz', 'sucursal'], $provider->names());
        self::assertSame('matriz', $provider->default()->name());
        self::assertSame('matriz', $provider->get()->name());
        self::assertSame('sucursal', $provider->get('sucursal')->name());
        self::assertTrue($provider->has('sucursal'));
        self::assertFalse($provider->has('inexistente'));
    }

    public function testExplicitDefaultProfile(): void
    {
        $provider = new CredentialsProvider(
            [
                'matriz' => new Credentials('matriz', 'u1', 'p1'),
                'sucursal' => new Credentials('sucursal', 'u2', 'p2'),
            ],
            'sucursal',
        );

        self::assertSame('sucursal', $provider->default()->name());
    }

    public function testRejectsUnknownDefaultProfile(): void
    {
        $this->expectException(ProfileNotFoundException::class);

        new CredentialsProvider(['matriz' => new Credentials('matriz', 'u', 'p')], 'inexistente');
    }

    public function testUnknownProfileThrowsWithAvailableNames(): void
    {
        $provider = new CredentialsProvider(['matriz' => new Credentials('matriz', 'u', 'p')]);

        try {
            $provider->get('sucursal');
            self::fail('Se esperaba ProfileNotFoundException.');
        } catch (ProfileNotFoundException $exception) {
            self::assertStringContainsString('sucursal', $exception->getMessage());
            self::assertStringContainsString('matriz', $exception->getMessage());
        }
    }

    public function testEmptyProviderFailsWithAnActionableMessage(): void
    {
        $provider = new CredentialsProvider();

        self::assertSame([], $provider->names());

        $this->expectException(ProfileNotFoundException::class);
        $this->expectExceptionMessageMatches('/al menos un perfil/');
        $provider->default();
    }

    public function testAcceptsAListOfProfilesUsingTheirNamesAsKeys(): void
    {
        $provider = new CredentialsProvider([
            new Credentials('matriz', 'u1', 'p1'),
            new Credentials('sucursal', 'u2', 'p2'),
        ]);

        self::assertSame(['matriz', 'sucursal'], $provider->names());
        self::assertSame('u2', $provider->get('sucursal')->username());
    }

    public function testAcceptsAnIterableOfProfiles(): void
    {
        $profiles = (static function (): \Generator {
            yield new Credentials('matriz', 'u1', 'p1');
        })();

        $provider = new CredentialsProvider($profiles);

        self::assertSame(['matriz'], $provider->names());
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts;
use Northrook\Contracts\Directory;
use Northrook\Contracts\LogicException;
use Northrook\Contracts\Redactor;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Secret;
use Northrook\Contracts\Singleton;
use Northrook\Contracts\Timezone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function Northrook\Contracts\get_checksum;

final class ContractsTest extends TestCase
{
    private const string ROOT = __DIR__ . '/..';

    protected function setUp(): void
    {
        $this->resetContracts();
        $this->clearEnvVars();
    }

    protected function tearDown(): void
    {
        $this->resetContracts();
        $this->clearEnvVars();
    }

    public function testRegisterReturnsRegisteredInstance(): void
    {
        self::assertFalse(Contracts::isRegistered());

        $contracts = Contracts::register(rootDirectory: self::ROOT);

        self::assertInstanceOf(Contracts::class, $contracts);
        self::assertTrue(Contracts::isRegistered());
        self::assertSame($contracts, Contracts::get());
    }

    public function testGetLazyRegistersViaCreate(): void
    {
        self::assertFalse(Contracts::isRegistered());

        $contracts = Contracts::get();

        self::assertInstanceOf(Contracts::class, $contracts);
        self::assertTrue(Contracts::isRegistered());
        self::assertSame($contracts, Contracts::get());
    }

    public function testRootDirectoryResolution(): void
    {
        $contracts = Contracts::register(rootDirectory: self::ROOT);

        self::assertInstanceOf(Directory::class, $contracts->rootDirectory);
        self::assertSame(
            \realpath(self::ROOT),
            $contracts->rootDirectory->value,
        );
    }

    public function testExplicitCacheDirectoryResolution(): void
    {
        $cache     = \sys_get_temp_dir();
        $contracts = Contracts::register(
            rootDirectory : self::ROOT,
            cacheDirectory: $cache,
        );

        self::assertSame(
            \realpath($cache),
            $contracts->cacheDirectory->value,
        );
    }

    public function testDefaultCacheDirectoryIsTempChecksumOfRoot(): void
    {
        $contracts = Contracts::register(rootDirectory: self::ROOT);

        $expected = \realpath(\sys_get_temp_dir()) . \DIR_SEP . get_checksum((string) \realpath(self::ROOT));

        self::assertSame($expected, $contracts->cacheDirectory->value);
    }

    public function testEnvRootOverride(): void
    {
        \putenv('APPROOT=' . self::ROOT);

        $contracts = Contracts::register();

        self::assertSame(
            \realpath(self::ROOT),
            $contracts->rootDirectory->value,
        );
    }

    public function testDefaultLoggerIsNullLogger(): void
    {
        $contracts = Contracts::register(rootDirectory: self::ROOT);

        self::assertInstanceOf(NullLogger::class, $contracts->logger);
    }

    public function testCustomLoggerIsRetained(): void
    {
        $logger    = $this->createMock(LoggerInterface::class);
        $contracts = Contracts::register(
            rootDirectory: self::ROOT,
            logger       : $logger,
        );

        self::assertSame($logger, $contracts->logger);
    }

    public function testDefaultTimezoneIsUtc(): void
    {
        $contracts = Contracts::register(rootDirectory: self::ROOT);

        self::assertInstanceOf(Timezone::class, $contracts->timezone);
        self::assertSame('UTC', $contracts->timezone->identifier);
    }

    public function testCustomTimezoneFromString(): void
    {
        $contracts = Contracts::register(
            rootDirectory: self::ROOT,
            timezone     : 'Europe/Oslo',
        );

        self::assertSame('Europe/Oslo', $contracts->timezone->identifier);
    }

    public function testOptionalServicesDefaultToNull(): void
    {
        $contracts = Contracts::register(rootDirectory: self::ROOT);

        self::assertNull($contracts->curl);
        self::assertNull($contracts->filesystem);
        self::assertNull($contracts->secretRedactor);
    }

    public function testCustomSecretRedactorIsRetainedAndUsed(): void
    {
        $redactor = new class() extends Redactor {
            protected function redact(
                mixed       $value,
                string      $type,
                null|string $condition = null,
            ): string {
                if ($condition === 'special-case' && $type === Secret::CREDENTIAL) {
                    return '[special-credential]';
                }

                return parent::redact($value, $type, $condition);
            }
        };

        $contracts = Contracts::register(
            rootDirectory : self::ROOT,
            secretRedactor: $redactor,
        );

        self::assertSame($redactor, $contracts->secretRedactor);
        self::assertSame(
            '[special-credential]',
            Secret::redact('postgres://…', Secret::CREDENTIAL, 'special-case'),
        );
        self::assertSame(
            '[Secret::string]',
            Secret::redact('hunter2', Secret::SENSITIVE),
        );
    }

    public function testSecondRegisterThrows(): void
    {
        Contracts::register(rootDirectory: self::ROOT);

        $this->expectException(RuntimeException::class);

        Contracts::register(rootDirectory: self::ROOT);
    }

    public function testCloneThrows(): void
    {
        $contracts = Contracts::register(rootDirectory: self::ROOT);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is a singleton and cannot be cloned.');

        // @phpstan-ignore-next-line Testing clone rejection.
        clone $contracts;
    }

    public function testRegisterWithNonexistentRootThrows(): void
    {
        $this->expectException(RuntimeException::class);

        Contracts::register(rootDirectory: '/nonexistent/contracts-test-root');
    }

    private function resetContracts(): void
    {
        $property = new \ReflectionProperty(Singleton::class, '__instance');
        $property->setValue(null, []);
    }

    private function clearEnvVars(): void
    {
        unset($_ENV['APPROOT'], $_ENV['PROJECT_ROOT']);
        \putenv('APPROOT');
        \putenv('PROJECT_ROOT');
    }
}

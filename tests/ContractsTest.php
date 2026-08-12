<?php /** @noinspection PhpExpressionResultUnusedInspection */

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts;
use Northrook\Contracts\Directory;
use Northrook\Contracts\LogicException;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Singleton;
use Northrook\Contracts\Value\Redactor;
use Northrook\Contracts\Value\Secret as SecretPolicy;
use Northrook\Contracts\Timezone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

    public function testExplicitVarDirectoryResolution(): void
    {
        $var       = \sys_get_temp_dir();
        $contracts = Contracts::register(
            rootDirectory: self::ROOT,
            varDirectory : $var,
        );

        self::assertSame(
            \realpath($var),
            $contracts->varDirectory->value,
        );
    }

    public function testDefaultVarDirectoryIsRootVar(): void
    {
        $contracts = Contracts::register(rootDirectory: self::ROOT);

        $expected = \realpath(self::ROOT) . \DIR_SEP . 'var';

        self::assertSame($expected, $contracts->varDirectory->value);
        self::assertDirectoryExists($contracts->varDirectory->value);
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
                mixed $value,
            ): mixed {
                if (
                    $this->secret->hasCondition('special-case')
                    && $this->secret->type === SecretPolicy::CREDENTIAL
                ) {
                    return '[special-credential]';
                }

                return parent::redact($value);
            }
        };

        $contracts = Contracts::register(
            rootDirectory : self::ROOT,
            secretRedactor: $redactor,
        );

        self::assertSame($redactor, $contracts->secretRedactor);
        self::assertSame(
            '[special-credential]',
            ( new SecretPolicy(SecretPolicy::CREDENTIAL, ['special-case']) )('postgres://…'),
        );
        self::assertSame(
            '[sensitive::string]',
            ( new SecretPolicy(SecretPolicy::SENSITIVE) )('hunter2'),
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

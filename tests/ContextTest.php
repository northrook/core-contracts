<?php /** @noinspection PhpExpressionResultUnusedInspection */

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Context;
use Northrook\Context\AppDebug;
use Northrook\Context\AppEnv;
use Northrook\Context\ContextManager;
use Northrook\Context\OsFamily;
use Northrook\Contracts\ContextEnum;
use Northrook\Contracts\Tests\Support\FilesystemStub;
use Northrook\CurlInterface;
use Northrook\Filesystem\Directory;
use Northrook\Filesystem\NativeFilesystem;
use Northrook\Filesystem\Path;
use Northrook\Filter;
use Northrook\Kernel\KernelContext;
use Northrook\Logger\NativeLogger;
use Northrook\LogicException;
use Northrook\Parameter\Secret as SecretPolicy;
use Northrook\Redactor;
use Northrook\RuntimeException;
use Northrook\Singleton;
use Northrook\Timezone;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ContextTest extends TestCase
{
    private const string ROOT = __DIR__ . '/..';

    protected function setUp(): void
    {
        $this->resetContext();
        $this->clearEnvVars();
    }

    protected function tearDown(): void
    {
        $this->resetContext();
        $this->clearEnvVars();
    }

    public function testRegisterReturnsRegisteredInstance(): void
    {
        self::assertFalse(Context::isRegistered());

        $context = Context::register(rootDirectory: self::ROOT);

        self::assertInstanceOf(Context::class, $context);
        self::assertTrue(Context::isRegistered());
        self::assertSame($context, Context::get());
    }

    public function testGetLazyRegistersViaCreate(): void
    {
        self::assertFalse(Context::isRegistered());

        $context = Context::get();

        self::assertInstanceOf(Context::class, $context);
        self::assertTrue(Context::isRegistered());
        self::assertSame($context, Context::get());
    }

    public function testTryGetReturnsNullWhenUnregistered(): void
    {
        self::assertFalse(Context::isRegistered());
        self::assertNull(Context::tryGet());
    }

    public function testTryGetReturnsInstanceWhenRegistered(): void
    {
        $context = Context::register(rootDirectory: self::ROOT);

        self::assertSame($context, Context::tryGet());
    }

    public function testBlindStaticResolversDoNotRegisterContext(): void
    {
        $appEnv   = AppEnv::resolve();
        $appDebug = AppDebug::resolve(appEnv: $appEnv);
        $osFamily = OsFamily::resolve();

        self::assertFalse(Context::isRegistered());
        self::assertSame($appEnv, Context::appEnv());
        self::assertSame($appDebug, Context::appDebug());
        self::assertSame($osFamily, Context::osFamily());
        self::assertFalse(Context::isRegistered());
    }

    public function testBlindEnvironmentPredicates(): void
    {
        $predicates = [
            [AppEnv::Production,  'isProduction'],
            [AppEnv::Development, 'isDevelopment'],
            [AppEnv::Testing,     'isTesting'],
            [AppEnv::Failsafe,    'isFailsafe'],
            [AppEnv::Staging,     'isStaging'],
        ];

        foreach ($predicates as [$appEnv, $expectedMethod]) {
            $this->setStaticContext('_appEnv', $appEnv);

            foreach ($predicates as [, $method]) {
                self::assertSame($method === $expectedMethod, Context::{$method}());
            }
        }

        self::assertFalse(Context::isRegistered());
    }

    public function testBlindDebugPredicate(): void
    {
        foreach (AppDebug::cases() as $appDebug) {
            $this->setStaticContext('_appDebug', $appDebug);

            self::assertSame($appDebug !== AppDebug::Disabled, Context::isDebug());
        }

        self::assertFalse(Context::isRegistered());
    }

    public function testBlindIsMatchesSystemContexts(): void
    {
        $this->setStaticContext('_appEnv', AppEnv::Staging);
        $this->setStaticContext('_appDebug', AppDebug::Verbose);
        $this->setStaticContext('_osFamily', OsFamily::Linux);

        self::assertTrue(Context::is(
            AppEnv::Staging,
            AppDebug::Verbose,
            OsFamily::Linux,
        ));
        self::assertFalse(Context::is(AppEnv::Production));
        self::assertFalse(Context::is(AppDebug::Disabled));
        self::assertFalse(Context::is(OsFamily::Windows));
        self::assertFalse(Context::isRegistered());
    }

    public function testIsWithoutValuesReturnsFalse(): void
    {
        self::assertFalse(Context::is());
        self::assertFalse(Context::isRegistered());
    }

    public function testFalseBlindMatchShortCircuitsRuntimeLookup(): void
    {
        $this->setStaticContext('_appEnv', AppEnv::Testing);

        self::assertFalse(Context::is(AppEnv::Production, KernelContext::Request));
        self::assertFalse(Context::isRegistered());
    }

    public function testRuntimeIsWithoutRegisteredContextLogsAndReturnsFalse(): void
    {
        self::assertFalse(Context::is(KernelContext::Request));
        self::assertFalse(Context::isRegistered());
    }

    public function testIsFilterOr(): void
    {
        $this->setStaticContext('_appEnv', AppEnv::Testing);
        $this->setStaticContext('_appDebug', AppDebug::Disabled);

        // OR: true because Testing matches, even though Production does not
        self::assertTrue(Context::is(
            AppEnv::Testing,
            AppEnv::Production,
            filter: Filter::OR,
        ));

        // OR: false when nothing matches
        self::assertFalse(Context::is(
            AppEnv::Production,
            AppEnv::Staging,
            filter: Filter::OR,
        ));

        self::assertFalse(Context::isRegistered());
    }

    public function testIsFilterNot(): void
    {
        $this->setStaticContext('_appEnv', AppEnv::Testing);

        // NOT: true when none match
        self::assertTrue(Context::is(
            AppEnv::Production,
            AppEnv::Staging,
            filter: Filter::NOT,
        ));

        // NOT: false when any matches
        self::assertFalse(Context::is(
            AppEnv::Testing,
            AppEnv::Production,
            filter: Filter::NOT,
        ));

        self::assertFalse(Context::isRegistered());
    }

    public function testIsFilterAnd(): void
    {
        $this->setStaticContext('_appEnv', AppEnv::Testing);
        $this->setStaticContext('_appDebug', AppDebug::Verbose);

        // AND: true when all match
        self::assertTrue(Context::is(
            AppEnv::Testing,
            AppDebug::Verbose,
            filter: Filter::AND,
        ));

        // AND: false when any does not match
        self::assertFalse(Context::is(
            AppEnv::Testing,
            AppDebug::Disabled,
            filter: Filter::AND,
        ));

        self::assertFalse(Context::isRegistered());
    }

    public function testRuntimeIsUsesRegisteredContextManager(): void
    {
        $manager = new ContextManager;
        Context::register(
            appEnv        : AppEnv::Testing,
            rootDirectory : self::ROOT,
            contextManager: $manager,
        );
        $manager->update(KernelContext::Request);

        self::assertTrue(Context::is(KernelContext::Request));
        self::assertTrue(Context::is(KernelContext::class));
        self::assertTrue(Context::is(AppEnv::Testing, KernelContext::Request));
        self::assertFalse(Context::is(KernelContext::Runtime));
    }

    public function testUnknownContextTypeLogsAndReturnsFalse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                self::stringContains('Unknown context type'),
                ['value' => UnsupportedContext::Value],
            );

        Context::register(
            rootDirectory: self::ROOT,
            logger       : $logger,
        );

        self::assertFalse(Context::is(UnsupportedContext::Value));
    }

    public function testRootDirectoryResolution(): void
    {
        $context = Context::register(rootDirectory: self::ROOT);

        self::assertInstanceOf(Directory::class, $context->rootDirectory);
        self::assertSame(
            \realpath(self::ROOT),
            $context->rootDirectory->value,
        );
    }

    public function testExplicitVarDirectoryResolution(): void
    {
        $var     = \sys_get_temp_dir();
        $context = Context::register(
            rootDirectory: self::ROOT,
            varDirectory : $var,
        );

        self::assertSame(
            \realpath($var),
            $context->varDirectory->value,
        );
    }

    public function testDefaultVarDirectoryIsRootVar(): void
    {
        $context = Context::register(rootDirectory: self::ROOT);

        $expected = \realpath(self::ROOT) . \DIR_SEP . 'var';

        self::assertSame($expected, $context->varDirectory->value);
        self::assertDirectoryExists($context->varDirectory->value);
    }

    public function testEnvRootOverride(): void
    {
        \putenv('APPROOT=' . self::ROOT);

        $context = Context::register();

        self::assertSame(
            \realpath(self::ROOT),
            $context->rootDirectory->value,
        );
    }

    public function testDefaultLoggerIsNativeLogger(): void
    {
        $context = Context::register(rootDirectory: self::ROOT);

        self::assertInstanceOf(NativeLogger::class, $context->logger);
    }

    public function testCustomLoggerIsRetained(): void
    {
        $logger  = $this->createStub(LoggerInterface::class);
        $context = Context::register(
            rootDirectory: self::ROOT,
            logger       : $logger,
        );

        self::assertSame($logger, $context->logger);
    }

    public function testDefaultTimezoneIsUtc(): void
    {
        $context = Context::register(rootDirectory: self::ROOT);

        self::assertInstanceOf(Timezone::class, $context->timezone);
        self::assertSame('UTC', $context->timezone->identifier);
    }

    public function testCustomTimezoneFromString(): void
    {
        $context = Context::register(
            rootDirectory: self::ROOT,
            timezone     : 'Europe/Oslo',
        );

        self::assertSame('Europe/Oslo', $context->timezone->identifier);
    }

    public function testOptionalServicesAndLazyRedactor(): void
    {
        $context = Context::register(rootDirectory: self::ROOT);

        self::assertInstanceOf(NativeFilesystem::class, $context->filesystem);
        self::assertSame($context->filesystem, $context->filesystem);
        self::assertNull($context->curlClient);
        self::assertInstanceOf(Redactor::class, $context->secretRedactor);
    }

    public function testProvidedCurlClientIsRetained(): void
    {
        $curl = $this->createStub(CurlInterface::class);

        $context = Context::register(
            rootDirectory: self::ROOT,
            curlClient   : $curl,
        );

        self::assertSame($curl, $context->curlClient);
    }

    public function testPathUsesRegisteredFilesystemByDefault(): void
    {
        $filesystem = new FilesystemStub;
        Context::register(
            rootDirectory: self::ROOT,
            filesystem   : $filesystem,
        );

        self::assertTrue(new Path(__FILE__)->exists());
        self::assertContains('fileExists', $filesystem->calls);
    }

    public function testCustomSecretRedactorIsRetainedAndUsed(): void
    {
        $redactor = new class() extends Redactor {
            protected function redact(
                mixed $value,
            ): mixed {
                if ($this->hasContext('special-case') && $this->secret === SecretPolicy::CREDENTIAL) {
                    return '[special-credential]';
                }

                return parent::redact($value);
            }
        };

        $context = Context::register(
            rootDirectory : self::ROOT,
            secretRedactor: $redactor,
        );

        self::assertSame($redactor, $context->secretRedactor);
        self::assertSame(
            '[special-credential]',
            $redactor('postgres://…', SecretPolicy::CREDENTIAL, ['special-case' => 'special-case']),
        );
        self::assertSame(
            '[secret::string:7]',
            $redactor('hunter2', SecretPolicy::SENSITIVE, []),
        );
    }

    public function testSecondRegisterThrows(): void
    {
        Context::register(rootDirectory: self::ROOT);

        $this->expectException(RuntimeException::class);

        Context::register(rootDirectory: self::ROOT);
    }

    public function testCloneThrows(): void
    {
        $context = Context::register(rootDirectory: self::ROOT);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is a singleton and cannot be cloned.');

        // @phpstan-ignore-next-line Testing clone rejection.
        clone $context;
    }

    public function testRegisterWithNonexistentRootThrows(): void
    {
        $this->expectException(RuntimeException::class);

        Context::register(rootDirectory: '/nonexistent/context-test-root');
    }

    private function resetContext(): void
    {
        $property = new \ReflectionProperty(Singleton::class, '_instance');
        $property->setValue(null, []);

        foreach (['_appEnv', '_appDebug', '_osFamily'] as $propertyName) {
            $this->setStaticContext($propertyName, null);
        }

        $property = new \ReflectionProperty(ContextManager::class, 'initialized');
        $property->setValue(null, false);
    }

    private function clearEnvVars(): void
    {
        unset($_ENV['APPROOT'], $_ENV['PROJECT_ROOT'], $_ENV['APP_ENV'], $_ENV['APP_DEBUG']);
        \putenv('APPROOT');
        \putenv('PROJECT_ROOT');
        \putenv('APP_ENV');
        \putenv('APP_DEBUG');
    }

    private function setStaticContext(
        string                        $propertyName,
        null|AppEnv|AppDebug|OsFamily $value,
    ): void {
        $property = new \ReflectionProperty(Context::class, $propertyName);
        $property->setValue(null, $value);
    }
}

enum UnsupportedContext implements ContextEnum
{
    case Value;
}

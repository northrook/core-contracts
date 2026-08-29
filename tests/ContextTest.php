<?php /** @noinspection PhpExpressionResultUnusedInspection */

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Context;
use Northrook\Context\AppDebug;
use Northrook\Context\AppEnv;
use Northrook\Context\OsFamily;
use Northrook\Contracts\ContextEnum;
use Northrook\Contracts\Tests\Support\ResetsContext;
use Northrook\Filter;
use Northrook\Instantiated;
use Northrook\Kernel\KernelContext;
use Northrook\Logger\NativeLogger;
use Northrook\RuntimeException;
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
        self::assertSame(self::ROOT, $context->rootDirectory);
        self::assertSame($context->rootDirectory, Context::rootDirectory());
    }

    public function testSecondRegisterThrows(): void
    {
        $first = Context::register(rootDirectory: self::ROOT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already registered');

        Context::register(
            rootDirectory: self::ROOT,
        );
    }

    public function testStaticAccessorsLazyRegister(): void
    {
        self::assertFalse(Context::isRegistered());

        $appEnv = Context::appEnv();

        self::assertTrue(Context::isRegistered());
        self::assertSame($appEnv, Context::appEnv());
        self::assertInstanceOf(AppDebug::class, Context::appDebug());
        self::assertInstanceOf(OsFamily::class, Context::osFamily());
        self::assertInstanceOf(Timezone::class, Context::timezone());
        self::assertInstanceOf(LoggerInterface::class, Context::logger());
        self::assertNotSame('', Context::rootDirectory());
        self::assertNotSame('', Context::varDirectory());

        $instance = $this->contextInstance();
        self::assertInstanceOf(Instantiated::class, $instance->instantiated);
        self::assertStringEndsWith('Context.php', $instance->instantiated->file);
        self::assertGreaterThan(0, $instance->instantiated->line);
    }

    public function testRegisterSetsDirectoriesTimezoneAndDefaults(): void
    {
        $var     = \sys_get_temp_dir();
        $context = Context::register(
            appEnv       : AppEnv::Testing,
            appDebug     : AppDebug::Disabled,
            rootDirectory: self::ROOT,
            varDirectory : $var,
            timezone     : 'Europe/Oslo',
        );

        self::assertSame(self::ROOT, Context::rootDirectory());
        self::assertSame($var, Context::varDirectory());
        self::assertSame('Europe/Oslo', Context::timezone()->identifier);
        self::assertSame(AppEnv::Testing, Context::appEnv());
        self::assertSame(AppDebug::Disabled, Context::appDebug());
        self::assertFalse(Context::isDebug());
        self::assertTrue(Context::isTesting());
        self::assertInstanceOf(NativeLogger::class, $context->logger);
        self::assertSame($context->logger, Context::logger());
    }

    public function testDefaultTimezoneIsUtc(): void
    {
        Context::register(rootDirectory: self::ROOT);

        self::assertSame('UTC', Context::timezone()->identifier);
    }

    public function testDefaultVarDirectoryIsRootVar(): void
    {
        Context::register(rootDirectory: self::ROOT);

        $expected = ( \realpath(self::ROOT) ?: self::ROOT ) . \DIR_SEP . 'var';

        self::assertSame($expected, Context::varDirectory());
    }

    public function testEnvRootOverride(): void
    {
        \putenv('APPROOT=' . self::ROOT);

        Context::register();

        self::assertSame(self::ROOT, Context::rootDirectory());
    }

    public function testSetLoggerAndTimezone(): void
    {
        $context = Context::register(rootDirectory: self::ROOT);
        $logger  = $this->createStub(LoggerInterface::class);

        $context->setLogger($logger);
        $context->setTimezone('America/New_York');

        self::assertSame($logger, Context::logger());
        self::assertSame('America/New_York', Context::timezone()->identifier);
    }

    public function testEnvironmentPredicates(): void
    {
        $predicates = [
            [AppEnv::Production,  'isProduction'],
            [AppEnv::Development, 'isDevelopment'],
            [AppEnv::Testing,     'isTesting'],
            [AppEnv::Failsafe,    'isFailsafe'],
            [AppEnv::Staging,     'isStaging'],
        ];

        foreach ($predicates as [$appEnv, $expectedMethod]) {
            $this->resetContext();
            Context::register(
                appEnv       : $appEnv,
                rootDirectory: self::ROOT,
            );

            foreach ($predicates as [, $method]) {
                self::assertSame($method === $expectedMethod, Context::{$method}());
            }
        }
    }

    public function testIsDebugTracksAppDebug(): void
    {
        foreach (AppDebug::cases() as $appDebug) {
            $this->resetContext();
            Context::register(
                appEnv       : AppEnv::Testing,
                appDebug     : $appDebug,
                rootDirectory: self::ROOT,
            );

            self::assertSame($appDebug !== AppDebug::Disabled, Context::isDebug());
        }
    }

    public function testIsWithoutValuesReturnsFalse(): void
    {
        self::assertFalse(Context::is());
        self::assertFalse(Context::isRegistered());
    }

    public function testIsMatchesRegisteredSystemContexts(): void
    {
        Context::register(
            appEnv       : AppEnv::Staging,
            appDebug     : AppDebug::Verbose,
            osFamily     : OsFamily::Linux,
            rootDirectory: self::ROOT,
        );

        self::assertTrue(Context::is(
            AppEnv::Staging,
            AppDebug::Verbose,
            OsFamily::Linux,
        ));
        self::assertFalse(Context::is(AppEnv::Production));
        self::assertFalse(Context::is(AppDebug::Disabled));
        self::assertFalse(Context::is(OsFamily::Windows));
    }

    public function testRuntimeIsWithoutRegisteredContextReturnsFalse(): void
    {
        self::assertFalse(Context::is(KernelContext::Request));
        self::assertFalse(Context::isRegistered());
    }

    public function testIsFilterOr(): void
    {
        Context::register(
            appEnv       : AppEnv::Testing,
            appDebug     : AppDebug::Disabled,
            rootDirectory: self::ROOT,
        );

        self::assertTrue(Context::is(
            AppEnv::Testing,
            AppEnv::Production,
            filter: Filter::OR,
        ));
        self::assertFalse(Context::is(
            AppEnv::Production,
            AppEnv::Staging,
            filter: Filter::OR,
        ));
    }

    public function testIsFilterNot(): void
    {
        Context::register(
            appEnv       : AppEnv::Testing,
            rootDirectory: self::ROOT,
        );

        self::assertTrue(Context::is(
            AppEnv::Production,
            AppEnv::Staging,
            filter: Filter::NOT,
        ));
        self::assertFalse(Context::is(
            AppEnv::Testing,
            AppEnv::Production,
            filter: Filter::NOT,
        ));
    }

    public function testIsFilterAnd(): void
    {
        Context::register(
            appEnv       : AppEnv::Testing,
            appDebug     : AppDebug::Verbose,
            rootDirectory: self::ROOT,
        );

        self::assertTrue(Context::is(
            AppEnv::Testing,
            AppDebug::Verbose,
            filter: Filter::AND,
        ));
        self::assertFalse(Context::is(
            AppEnv::Testing,
            AppDebug::Disabled,
            filter: Filter::AND,
        ));
    }

    public function testRuntimeIsUsesRegisteredContextManager(): void
    {
        $context = Context::register(
            appEnv       : AppEnv::Testing,
            rootDirectory: self::ROOT,
        );
        $context->update(KernelContext::Request);

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
            ->method('log')
            ->with(
                self::anything(),
                self::stringContains('Unknown context type'),
                ['value' => UnsupportedContext::Value],
            );

        Context::register(rootDirectory: self::ROOT)->setLogger($logger);

        self::assertFalse(Context::is(UnsupportedContext::Value));
    }

    public function testFailsafeIsUntrusted(): void
    {
        Context::register(
            appEnv       : AppEnv::Failsafe,
            rootDirectory: self::ROOT,
        );

        self::assertTrue(Context::isUntrusted());
        self::assertFalse(Context::isTrusted());
    }

    public function testHttpKernelContextIsUntrusted(): void
    {
        $context = Context::register(
            appEnv       : AppEnv::Testing,
            rootDirectory: self::ROOT,
        );

        self::assertTrue(Context::isTrusted());

        $context->update(KernelContext::Request);

        self::assertTrue(Context::isUntrusted());
        self::assertFalse(Context::isTrusted());
    }

    private function contextInstance(): Context
    {
        $property = new \ReflectionProperty(Context::class, 'instance');

        self::assertTrue($property->isInitialized());

        $instance = $property->getValue();
        self::assertInstanceOf(Context::class, $instance);

        return $instance;
    }

    private function resetContext(): void
    {
        ResetsContext::reset();
    }

    private function clearEnvVars(): void
    {
        unset($_ENV['APPROOT'], $_ENV['PROJECT_ROOT'], $_ENV['APP_ENV'], $_ENV['APP_DEBUG']);
        \putenv('APPROOT');
        \putenv('PROJECT_ROOT');
        \putenv('APP_ENV');
        \putenv('APP_DEBUG');
    }
}

enum UnsupportedContext implements ContextEnum
{
    case Value;
}

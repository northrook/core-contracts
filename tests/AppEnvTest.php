<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\AppEnv;
use Northrook\AppEnvironment;
use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\ErrorHandler\ErrorSnapshot;
use Northrook\Contracts\Exceptions\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppEnvTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetAppEnv();
        $this->clearEnvVars();
    }

    protected function tearDown(): void
    {
        $this->resetAppEnv();
        $this->clearEnvVars();
    }

    public function testIsNotInitializedBeforeFirstUse(): void
    {
        self::assertFalse(AppEnv::isInitialized());
    }

    public function testLazyInitializationDefaultsToTestingUnderPhpunit(): void
    {
        self::assertSame(AppEnvironment::Testing, AppEnv::getEnvironment());
        self::assertTrue(AppEnv::isTesting());
        self::assertTrue(AppEnv::isInitialized());
    }

    public function testConstructorAcceptsEnum(): void
    {
        new AppEnv(environment: AppEnvironment::Staging);

        self::assertSame(AppEnvironment::Staging, AppEnv::getEnvironment());
        self::assertTrue(AppEnv::isStaging());
    }

    #[DataProvider('provideResolvableEnvironments')]
    public function testConstructorResolvesStringEnvironment(
        string $input,
        AppEnvironment $expected,
    ): void {
        new AppEnv(environment: $input);

        self::assertSame($expected, AppEnv::getEnvironment());
    }

    /**
     * @return iterable<string, array{string, AppEnvironment}>
     */
    public static function provideResolvableEnvironments(): iterable
    {
        yield 'production' => ['production', AppEnvironment::Production];
        yield 'dev alias' => ['dev', AppEnvironment::Development];
        yield 'unknown' => ['sandbox', AppEnvironment::Failsafe];
    }

    public function testConstructorRejectsSecondInstance(): void
    {
        new AppEnv(environment: AppEnvironment::Development);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('singleton');

        new AppEnv(environment: AppEnvironment::Production);
    }

    #[DataProvider('provideEnvironmentPredicates')]
    public function testEnvironmentPredicates(
        AppEnvironment $environment,
        bool $isProduction,
        bool $isDevelopment,
        bool $isTesting,
        bool $isStaging,
        bool $isFailsafe,
    ): void {
        new AppEnv(environment: $environment);

        self::assertSame($isProduction, AppEnv::isProduction());
        self::assertSame($isDevelopment, AppEnv::isDevelopment());
        self::assertSame($isTesting, AppEnv::isTesting());
        self::assertSame($isStaging, AppEnv::isStaging());
        self::assertSame($isFailsafe, AppEnv::isFailsafe());
    }

    /**
     * @return iterable<string, array{
     *     AppEnvironment,
     *     bool,
     *     bool,
     *     bool,
     *     bool,
     *     bool,
     * }>
     */
    public static function provideEnvironmentPredicates(): iterable
    {
        yield 'production' => [AppEnvironment::Production, true, false, false, false, false];
        yield 'development' => [AppEnvironment::Development, false, true, false, false, false];
        yield 'testing' => [AppEnvironment::Testing, false, false, true, false, false];
        yield 'staging' => [AppEnvironment::Staging, false, false, false, true, false];
        yield 'failsafe' => [AppEnvironment::Failsafe, false, false, false, false, true];
    }

    public function testExplicitDebugFlag(): void
    {
        new AppEnv(environment: AppEnvironment::Development, debug: true);

        self::assertTrue(AppEnv::isDebug());
    }

    public function testFailsafeForcesDebugOff(): void
    {
        new AppEnv(environment: AppEnvironment::Failsafe, debug: true);

        self::assertFalse(AppEnv::isDebug());
    }

    #[DataProvider('provideStringDebugValues')]
    public function testResolvesDebugFromStringEnv(
        string $value,
        bool $expected,
    ): void {
        $_ENV['APP_DEBUG'] = $value;

        new AppEnv(environment: AppEnvironment::Development);

        self::assertSame($expected, AppEnv::isDebug());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideStringDebugValues(): iterable
    {
        yield 'true' => ['true', true];
        yield '1' => ['1', true];
        yield 'yes' => ['yes', true];
        yield 'false' => ['false', false];
        yield '0' => ['0', false];
        yield 'no' => ['no', false];
    }

    public function testErrorReportCapturesCurrentEnvironment(): void
    {
        new AppEnv(environment: AppEnvironment::Production);

        $report = new ErrorReport(
            reference   : 'error-env',
            timestamp   : 1_700_000_000.0,
            severity    : 'error',
            error       : ErrorSnapshot::from(
                class: \RuntimeException::class,
                message: 'failure',
                code: 0,
                file: '/tmp/example.php',
                line: 1,
            ),
            stackFrames : [],
        );

        self::assertSame(AppEnvironment::Production, $report->environment);
        self::assertSame('production', $report->jsonSerialize()['environment']);
    }

    private function resetAppEnv(): void
    {
        $property = new \ReflectionProperty(AppEnv::class, 'instance');
        $property->setValue(null, null);
    }

    private function clearEnvVars(): void
    {
        unset($_ENV['APP_ENV'], $_ENV['APP_DEBUG']);
        \putenv('APP_ENV');
        \putenv('APP_DEBUG');
    }
}

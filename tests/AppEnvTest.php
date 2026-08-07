<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\AppEnv;
use Northrook\AppEnvironment;
use Northrook\Contracts\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * AppEnv process probes and lazy env resolution.
 *
 * Pest / Codeception branches of {@see AppEnv::isTestRunner()} share the same OR
 * logic; this suite exercises the live PHPUnit path only.
 */
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

    public function testIsTestRunnerTrueUnderPhpunit(): void
    {
        self::assertTrue(AppEnv::isTestRunner());
    }

    public function testLazyInitDefaultsToTestingUnderTestRunner(): void
    {
        self::assertFalse(AppEnv::isInitialized());
        self::assertSame(AppEnvironment::Testing, AppEnv::getEnvironment());
        self::assertTrue(AppEnv::isTesting());
        self::assertTrue(AppEnv::isInitialized());
    }

    public function testIsCliMatchesPhpSapi(): void
    {
        $expected = \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';

        self::assertSame($expected, AppEnv::isCli());
    }

    #[DataProvider('provideProcessProbeSmokes')]
    public function testProcessProbeSmoke(
        string $label,
        bool   $actual,
        bool   $expected,
    ): void {
        self::assertSame($expected, $actual, $label);
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function provideProcessProbeSmokes(): iterable
    {
        yield 'isWeb xor isCli' => [
            'isWeb must be the inverse of isCli',
            AppEnv::isWeb(),
            ! AppEnv::isCli(),
        ];

        yield 'isSapi current' => [
            'isSapi(PHP_SAPI) must be true',
            AppEnv::isSapi(\PHP_SAPI),
            true,
        ];

        yield 'isFpm matches sapi' => [
            'isFpm must match PHP_SAPI',
            AppEnv::isFpm(),
            \PHP_SAPI === 'fpm-fcgi',
        ];

        yield 'isCgi matches sapi' => [
            'isCgi must match PHP_SAPI',
            AppEnv::isCgi(),
            \PHP_SAPI === 'cgi' || \PHP_SAPI === 'cgi-fcgi',
        ];
    }

    // -------------------------------------------------------------------------
    // Constructor / resolution
    // -------------------------------------------------------------------------

    public function testConstructorAcceptsEnum(): void
    {
        $appEnv = new AppEnv(AppEnvironment::Staging);

        self::assertSame(AppEnvironment::Staging, $appEnv->environment);
        self::assertTrue(AppEnv::isStaging());
        self::assertTrue(AppEnv::isInitialized());
    }

    #[DataProvider('provideResolvableEnvironments')]
    public function testConstructorResolvesStringEnvironment(
        string         $input,
        AppEnvironment $expected,
    ): void {
        $appEnv = new AppEnv($input);

        self::assertSame($expected, $appEnv->environment);
        self::assertSame($expected, AppEnv::getEnvironment());
    }

    /**
     * @return \Generator<string, array{string, AppEnvironment}>
     */
    public static function provideResolvableEnvironments(): \Generator
    {
        yield 'production' => ['production', AppEnvironment::Production];
        yield 'dev alias' => ['dev', AppEnvironment::Development];
        yield 'unknown falls back to failsafe' => ['sandbox', AppEnvironment::Failsafe];
    }

    public function testExplicitNullEnvironmentResolvesUnderTestRunner(): void
    {
        // Under PHPUnit, `null` resolves to Testing before APP_ENV is consulted.
        $_ENV['APP_ENV'] = 'production';

        $appEnv = new AppEnv;

        self::assertSame(AppEnvironment::Testing, $appEnv->environment);
    }

    public function testConstructorRejectsSecondInstance(): void
    {
        new AppEnv(AppEnvironment::Development);

        $this->expectException(RuntimeException::class);

        new AppEnv(AppEnvironment::Development);
    }

    // -------------------------------------------------------------------------
    // Environment predicates
    // -------------------------------------------------------------------------

    #[DataProvider('provideEnvironmentPredicates')]
    public function testEnvironmentPredicates(
        AppEnvironment $environment,
        bool           $isDevelopment,
        bool           $isProduction,
        bool           $isTesting,
        bool           $isStaging,
        bool           $isFailsafe,
    ): void {
        new AppEnv($environment);

        self::assertSame($isDevelopment, AppEnv::isDevelopment());
        self::assertSame($isProduction, AppEnv::isProduction());
        self::assertSame($isTesting, AppEnv::isTesting());
        self::assertSame($isStaging, AppEnv::isStaging());
        self::assertSame($isFailsafe, AppEnv::isFailsafe());
    }

    /**
     * @return \Generator<string, array{AppEnvironment, bool, bool, bool, bool, bool}>
     */
    public static function provideEnvironmentPredicates(): \Generator
    {
        yield 'production' => [AppEnvironment::Production, false, true, false, false, false];
        yield 'development' => [AppEnvironment::Development, true, false, false, false, false];
        yield 'testing' => [AppEnvironment::Testing, false, false, true, false, false];
        yield 'staging' => [AppEnvironment::Staging, false, false, false, true, false];
        yield 'failsafe' => [AppEnvironment::Failsafe, false, false, false, false, true];
    }

    // -------------------------------------------------------------------------
    // Debug resolution
    // -------------------------------------------------------------------------

    public function testExplicitDebugFlag(): void
    {
        $appEnv = new AppEnv(AppEnvironment::Development, debug: true);

        self::assertTrue($appEnv->debug);
        self::assertTrue(AppEnv::isDebug());
    }

    public function testExplicitDebugFlagOff(): void
    {
        $appEnv = new AppEnv(AppEnvironment::Development, debug: false);

        self::assertFalse($appEnv->debug);
        self::assertFalse(AppEnv::isDebug());
    }

    public function testDebugDefaultsToFalseWithoutEnv(): void
    {
        $appEnv = new AppEnv(AppEnvironment::Production);

        self::assertFalse($appEnv->debug);
    }

    public function testFailsafeForcesDebugOff(): void
    {
        $appEnv = new AppEnv(AppEnvironment::Failsafe, debug: true);

        self::assertFalse($appEnv->debug);
        self::assertFalse(AppEnv::isDebug());
    }

    #[DataProvider('provideStringDebugValues')]
    public function testResolvesDebugFromStringEnv(
        string $value,
        bool   $expected,
    ): void {
        $_ENV['APP_DEBUG'] = $value;

        $appEnv = new AppEnv(AppEnvironment::Development);

        self::assertSame($expected, $appEnv->debug);
        self::assertSame($expected, AppEnv::isDebug());
    }

    public function testEnvZeroDisablesDebugViaGetenvAlone(): void
    {
        unset($_ENV['APP_DEBUG']);
        \putenv('APP_DEBUG=0');

        $appEnv = new AppEnv(AppEnvironment::Development);

        self::assertFalse($appEnv->debug);
    }

    public function testEnvZeroOverridesTrueAppDebugConstant(): void
    {
        $script = \tempnam(\sys_get_temp_dir(), 'appenv-');
        self::assertNotFalse($script);

        try {
            $autoload = \var_export(\dirname(__DIR__) . '/vendor/autoload.php', true);
            \file_put_contents($script, <<<PHP
                <?php

                declare(strict_types=1);

                define('APP_DEBUG', true);
                \$_ENV['APP_DEBUG'] = '0';
                require {$autoload};
                \$app = new Northrook\\AppEnv(Northrook\\AppEnvironment::Development);
                echo \$app->debug ? '1' : '0';
                PHP);

            $result = \shell_exec(\escapeshellarg(\PHP_BINARY) . ' ' . \escapeshellarg($script));

            self::assertSame('0', $result);
        } finally {
            @\unlink($script);
        }
    }

    /**
     * @return \Generator<string, array{string, bool}>
     */
    public static function provideStringDebugValues(): \Generator
    {
        yield 'true' => ['true', true];
        yield 'one' => ['1', true];
        yield 'yes' => ['yes', true];
        yield 'false' => ['false', false];
        yield 'zero' => ['0', false];
        yield 'no' => ['no', false];
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

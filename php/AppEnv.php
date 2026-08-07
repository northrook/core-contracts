<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\RuntimeException;

/**
 * Singleton for the process application environment and debug flag.
 *
 * Lazy-initializes on first access ({@see getEnvironment()}, {@see isDebug()}, …).
 * Construct explicitly once to pin values; a second construction throws.
 *
 * Environment resolution order when the constructor argument is omitted / a string:
 *  1. Explicit string → {@see AppEnvironment::parse()}
 *  2. Else, when {@see isTestRunner()} is true → {@see AppEnvironment::Testing}
 *  3. Else `$_ENV['APP_ENV']` / `getenv('APP_ENV')`
 *  4. Else the `APP_ENV` constant, if defined
 *  5. Else {@see AppEnvironment::Failsafe}
 *
 * Step 2 is intentional: known PHP test suites (PHPUnit, Pest, Codeception) default
 * to {@see AppEnvironment::Testing} and ignore a surrounding `APP_ENV`. To exercise
 * another environment under a test runner — including production conditions —
 * pass it explicitly to the constructor (`new AppEnv(AppEnvironment::Production)`
 * or `new AppEnv('production')`).
 *
 * Debug resolution ({@see resolveDebug()}) still honors `APP_DEBUG` under test runners.
 * {@see AppEnvironment::Failsafe} always forces debug off.
 */
final class AppEnv
{
    private static null|AppEnv $instance = null;

    public readonly AppEnvironment $environment;

    public readonly bool $debug;

    public function __construct(
        null|string|AppEnvironment $environment = null,
        null|bool                  $debug = null,
        private readonly bool      $selfInstantiated = false,
    ) {
        $this->environment = match (true) {
            $environment instanceof AppEnvironment => $environment,
            default => $this->resolveEnv($environment),
        };
        $this->debug = $this->resolveDebug($debug);

        if (static::$instance !== null) {
            throw new RuntimeException(
                message: $this::class . ' is a singleton and cannot be instantiated twice.',
                context: [
                    'instance'  => static::$instance,
                    'class'     => $this,
                    'timestamp' => \hrtime(true),
                    'anonymous' => $this->selfInstantiated,
                ],
            );
        }
        static::$instance = $this;
    }

    public static function getEnvironment(): AppEnvironment
    {
        return self::instance()->environment;
    }

    // -------------------------------------------------------------------------
    // Process / SAPI (instance-free)
    // -------------------------------------------------------------------------

    /**
     * Whether the current SAPI is CLI or phpdbg.
     */
    public static function isCli(): bool
    {
        return \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
    }

    /**
     * Whether the current SAPI is PHP's built-in development server.
     */
    public static function isCliServer(): bool
    {
        return \PHP_SAPI === 'cli-server';
    }

    /**
     * Whether the current SAPI is HTTP-facing (not CLI/phpdbg).
     */
    public static function isWeb(): bool
    {
        return ! self::isCli();
    }

    /**
     * Whether the current SAPI is PHP-FPM.
     */
    public static function isFpm(): bool
    {
        return \PHP_SAPI === 'fpm-fcgi';
    }

    /**
     * Whether the current SAPI is CGI or CGI-FastCGI.
     */
    public static function isCgi(): bool
    {
        return \PHP_SAPI === 'cgi' || \PHP_SAPI === 'cgi-fcgi';
    }

    /**
     * Whether the current SAPI matches `$sapi` exactly.
     */
    public static function isSapi(
        string $sapi,
    ): bool {
        return \PHP_SAPI === $sapi;
    }

    /**
     * Whether a known test runner is active in this process.
     *
     * Detects PHPUnit (composer install or phar), Pest, and Codeception.
     * Does not autoload third-party classes — presence only.
     */
    public static function isTestRunner(): bool
    {
        if (\defined('PHPUNIT_COMPOSER_INSTALL') || \defined('__PHPUNIT_PHAR__')) {
            return true;
        }

        if (\defined('PEST') || \class_exists('\Pest\Tester', false) || \class_exists('\Pest\TestSuite', false)) {
            return true;
        }

        return \defined('CODECEPTION_VERSION') || \class_exists('\Codeception\Codecept', false);
    }

    /**
     * Whether Composer reports a development install (`COMPOSER_DEV_MODE`).
     */
    public static function isComposerDev(): bool
    {
        if (\defined('COMPOSER_DEV_MODE')) {
            return \filter_var(\COMPOSER_DEV_MODE, \FILTER_VALIDATE_BOOLEAN);
        }

        $env = $_ENV['COMPOSER_DEV_MODE'] ?? \getenv('COMPOSER_DEV_MODE');

        if ($env === false || $env === '') {
            return false;
        }

        return \filter_var($env, \FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether STDIN exists and is an interactive TTY.
     */
    public static function isInteractive(): bool
    {
        if (! \defined('STDIN')) {
            return false;
        }

        /** @var resource $stdin */
        $stdin = \STDIN;

        if (\function_exists('stream_isatty')) {
            return \stream_isatty($stdin);
        }

        if (\function_exists('posix_isatty')) {
            return \posix_isatty($stdin);
        }

        return false;
    }

    /**
     * Whether the process effective user is root (posix only).
     */
    public static function isRoot(): bool
    {
        if (! \function_exists('posix_geteuid')) {
            return false;
        }

        return \posix_geteuid() === 0;
    }

    /**
     * Whether the current script is running inside a Phar archive.
     */
    public static function isPhar(): bool
    {
        return \class_exists(\Phar::class, false) && \Phar::running() !== '';
    }

    /**
     * Whether Xdebug is loaded and not effectively off.
     */
    public static function isXdebugEnabled(): bool
    {
        if (! \extension_loaded('xdebug')) {
            return false;
        }

        if (\function_exists('xdebug_is_debugger_active') && \xdebug_is_debugger_active()) {
            return true;
        }

        if (\function_exists('xdebug_info')) {
            /**
             * @var mixed $modes
             *
             * @noinspection PhpVoidFunctionResultUsedInspection
             */
            $modes = @\xdebug_info('mode');

            if (\is_array($modes)) {
                return $modes !== [] && ! ( \count($modes) === 1 && ( $modes[0] ?? null ) === 'off' );
            }
        }

        $mode = (string) \ini_get('xdebug.mode');

        if ($mode === '' || \strtolower($mode) === 'off') {
            return false;
        }

        return true;
    }

    /**
     * Whether the PCOV coverage extension is loaded.
     */
    public static function isPcovLoaded(): bool
    {
        return \extension_loaded('pcov');
    }

    /**
     * Whether coverage/debug tooling is active (Xdebug enabled or PCOV loaded).
     */
    public static function isDebugProbeActive(): bool
    {
        return self::isXdebugEnabled() || self::isPcovLoaded();
    }

    // -------------------------------------------------------------------------
    // Application environment (instance-backed)
    // -------------------------------------------------------------------------

    public static function isDebug(): bool
    {
        return self::instance()->debug;
    }

    final public static function isDevelopment(): bool
    {
        return self::instance()->environment === AppEnvironment::Development;
    }

    final public static function isProduction(): bool
    {
        return self::instance()->environment === AppEnvironment::Production;
    }

    final public static function isTesting(): bool
    {
        return self::instance()->environment === AppEnvironment::Testing;
    }

    final public static function isStaging(): bool
    {
        return self::instance()->environment === AppEnvironment::Staging;
    }

    final public static function isFailsafe(): bool
    {
        return self::instance()->environment === AppEnvironment::Failsafe;
    }

    public static function isInitialized(): bool
    {
        return isset(self::$instance);
    }

    private static function instance(): AppEnv
    {
        return static::$instance ??= new static(selfInstantiated: true);
    }

    /**
     * Resolve the environment slug; see class docblock for precedence (test-runner first).
     */
    private function resolveEnv(
        null|string $env,
    ): AppEnvironment {
        $env ??= self::isTestRunner()
            ? AppEnvironment::Testing->value
            : null;

        $env ??= $_ENV['APP_ENV'] ?? \getenv('APP_ENV') ?: null;

        $env ??= defined('APP_ENV') ? \APP_ENV : null;

        if (! is_string($env)) {
            return AppEnvironment::Failsafe;
        }

        return AppEnvironment::parse($env);
    }

    private function resolveDebug(
        null|bool $debug,
    ): bool {
        if ($this->environment === AppEnvironment::Failsafe) {
            return false;
        }

        // Do not use `?:` here — `"0"` must remain a set env value (falsy but valid).
        $debug ??= match (true) {
            \array_key_exists('APP_DEBUG', $_ENV) => $_ENV['APP_DEBUG'],
            false !== ( $value = \getenv('APP_DEBUG') ) => $value,
            default => null,
        };

        $debug ??= defined('APP_DEBUG') ? \APP_DEBUG : false;

        if (is_bool($debug)) {
            return $debug;
        }

        return (bool) \filter_var($debug, \FILTER_VALIDATE_BOOLEAN);
    }
}

<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\AppEnvironment;
use Northrook\Contracts\RuntimeException;

/**
 * Process-wide application environment and exposure flags.
 *
 * Singleton: construct once to pin values, or let the first static accessor
 * ({@see getEnvironment()}, {@see isDebug()}, {@see isPublic()}, …) lazy-init.
 * A second construction throws {@see RuntimeException}.
 *
 * ## Stage — {@see $environment}
 *
 * When the constructor `$environment` is omitted or a string:
 *  1. Explzicit string → {@see AppEnvironment::parse()}
 *  2. Else, when {@see isTestRunner()} → {@see AppEnvironment::Testing}
 *  3. Else `$_ENV` / `getenv` for {@see APP_ENV}
 *  4. Else the `APP_ENV` PHP constant, if defined
 *  5. Else {@see AppEnvironment::Failsafe}
 *
 * Step 2 is intentional: PHPUnit / Pest / Codeception default to Testing and
 * ignore a surrounding `APP_ENV`. To exercise another stage under a test runner
 * (including production), pass it explicitly:
 * `new AppEnv(AppEnvironment::Production)` or `new AppEnv('production')`.
 *
 * ## Flags — {@see $debug}, {@see $public}
 *
 * Orthogonal unless Failsafe intervenes. Resolution for each (via
 * {@see resolveBoolFlag()}):
 *  1. Explicit constructor bool
 *  2. Else `$_ENV` / `getenv` for {@see APP_DEBUG} or {@see APP_PUBLIC}
 *  3. Else the matching PHP constant, if defined
 *  4. Else `false`
 *
 * Env / constant lookup still runs under test runners. String values use
 * {@see \filter_var()} with `FILTER_VALIDATE_BOOLEAN` (`"0"` / `"false"` stay off).
 *
 * - **debug** — process wants verbose diagnostics (logging, dumps, rich errors).
 * - **public** — output may leave the trust boundary (HTTP response, webhook,
 *   email, export). Opt-in; not derived from {@see isWeb()}.
 *
 * {@see AppEnvironment::Failsafe} assumes worst-case exposure: forces
 * `debug === false` and `public === true`, ignoring constructor / env / constants.
 *
 * ## Process probes
 *
 * SAPI and tooling helpers (`isCli()`, `isWeb()`, `isTestRunner()`, …) are
 * instance-free and do not trigger lazy init.
 */
final class AppEnv
{
    /** Env / PHP constant name for the application stage slug. */
    public const string APP_ENV = 'APP_ENV';

    /** Env / PHP constant name for the debug flag. */
    public const string APP_DEBUG = 'APP_DEBUG';

    /** Env / PHP constant name for the public (outward-facing) flag. */
    public const string APP_PUBLIC = 'APP_PUBLIC';

    private static null|AppEnv $instance = null;

    /**
     * Resolved deployment / runtime stage.
     */
    public readonly AppEnvironment $environment;

    /**
     * Whether the process wants verbose diagnostics.
     *
     * Forced `false` under {@see AppEnvironment::Failsafe}.
     */
    public readonly bool $debug;

    /**
     * Whether work product may leave the trust boundary.
     *
     * Forced `true` under {@see AppEnvironment::Failsafe}.
     */
    public readonly bool $public;

    /**
     * @param null|string|AppEnvironment $environment  Explicit stage, slug, or `null` to resolve
     * @param null|bool                  $debug        Explicit debug, or `null` to resolve; ignored under Failsafe
     * @param null|bool                  $public       Explicit public, or `null` to resolve; ignored under Failsafe
     * @param bool                       $selfInstantiated  Internal: lazy init via {@see instance()}
     *
     * @throws RuntimeException when an instance already exists
     */
    public function __construct(
        null|string|AppEnvironment $environment = null,
        null|bool                  $debug = null,
        null|bool                  $public = null,
        private readonly bool      $selfInstantiated = false,
    ) {
        $this->environment = match (true) {
            $environment instanceof AppEnvironment => $environment,
            default => $this->resolveEnv($environment),
        };
        $this->debug  = $this->resolveDebug($debug);
        $this->public = $this->resolvePublic($public);

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

    /** Resolved {@see $environment}, lazy-initializing if needed. */
    public static function getEnvironment(): AppEnvironment
    {
        return self::instance()->environment;
    }

    // -------------------------------------------------------------------------
    // Process / SAPI (instance-free)
    // -------------------------------------------------------------------------

    /** Whether the current SAPI is CLI or phpdbg. */
    public static function isCli(): bool
    {
        return \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
    }

    /** Whether the current SAPI is PHP's built-in development server. */
    public static function isCliServer(): bool
    {
        return \PHP_SAPI === 'cli-server';
    }

    /**
     * Whether the current SAPI is HTTP-facing (not CLI/phpdbg).
     *
     * Process capability only — not the {@see $public} policy flag.
     */
    public static function isWeb(): bool
    {
        return ! self::isCli();
    }

    /** Whether the current SAPI is PHP-FPM. */
    public static function isFpm(): bool
    {
        return \PHP_SAPI === 'fpm-fcgi';
    }

    /** Whether the current SAPI is CGI or CGI-FastCGI. */
    public static function isCgi(): bool
    {
        return \PHP_SAPI === 'cgi' || \PHP_SAPI === 'cgi-fcgi';
    }

    /** Whether the current SAPI matches `$sapi` exactly. */
    public static function isSapi(
        string $sapi,
    ): bool {
        return \PHP_SAPI === $sapi;
    }

    /**
     * Whether a known test runner is active in this process.
     *
     * Detects PHPUnit (Composer install or phar), Pest, and Codeception.
     * Does not autoload third-party classes — presence / defined symbols only.
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
     *
     * Checks the constant first, then `$_ENV` / `getenv`.
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

    /** Whether STDIN exists and is an interactive TTY. */
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

    /** Whether the process effective user is root (POSIX only; otherwise false). */
    public static function isRoot(): bool
    {
        if (! \function_exists('posix_geteuid')) {
            return false;
        }

        return \posix_geteuid() === 0;
    }

    /** Whether the current script is running inside a Phar archive. */
    public static function isPhar(): bool
    {
        return \class_exists(\Phar::class, false) && \Phar::running() !== '';
    }

    /**
     * Whether Xdebug is loaded and not effectively off.
     *
     * Prefers an active debugger, then `xdebug_info('mode')`, then `xdebug.mode` ini.
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

    /** Whether the PCOV coverage extension is loaded. */
    public static function isPcovLoaded(): bool
    {
        return \extension_loaded('pcov');
    }

    /** Whether coverage / debug tooling is active ({@see isXdebugEnabled()} or {@see isPcovLoaded()}). */
    public static function isDebugProbeActive(): bool
    {
        return self::isXdebugEnabled() || self::isPcovLoaded();
    }

    // -------------------------------------------------------------------------
    // Application environment (instance-backed)
    // -------------------------------------------------------------------------

    /** {@see $debug}, lazy-initializing if needed. */
    public static function isDebug(): bool
    {
        return self::instance()->debug;
    }

    /** {@see $public}, lazy-initializing if needed. */
    public static function isPublic(): bool
    {
        return self::instance()->public;
    }

    /** Whether {@see $environment} is {@see AppEnvironment::Development}. */
    final public static function isDevelopment(): bool
    {
        return self::instance()->environment === AppEnvironment::Development;
    }

    /** Whether {@see $environment} is {@see AppEnvironment::Production}. */
    final public static function isProduction(): bool
    {
        return self::instance()->environment === AppEnvironment::Production;
    }

    /** Whether {@see $environment} is {@see AppEnvironment::Testing}. */
    final public static function isTesting(): bool
    {
        return self::instance()->environment === AppEnvironment::Testing;
    }

    /** Whether {@see $environment} is {@see AppEnvironment::Staging}. */
    final public static function isStaging(): bool
    {
        return self::instance()->environment === AppEnvironment::Staging;
    }

    /** Whether {@see $environment} is {@see AppEnvironment::Failsafe}. */
    final public static function isFailsafe(): bool
    {
        return self::instance()->environment === AppEnvironment::Failsafe;
    }

    /** Whether the singleton has been constructed (explicitly or via lazy init). */
    public static function isInitialized(): bool
    {
        return isset(self::$instance);
    }

    /**
     * Singleton accessor; constructs with defaults when unset.
     *
     * @internal
     */
    private static function instance(): AppEnv
    {
        return static::$instance ??= new static(selfInstantiated: true);
    }

    /**
     * Resolve a stage slug to {@see AppEnvironment}.
     *
     * Precedence is documented on the class (test-runner before env).
     * Non-string outcomes after lookup → {@see AppEnvironment::Failsafe}.
     */
    private function resolveEnv(
        null|string $env,
    ): AppEnvironment {
        $env ??= self::isTestRunner()
            ? AppEnvironment::Testing->value
            : null;

        $env ??= $_ENV[self::APP_ENV] ?? \getenv(self::APP_ENV) ?: null;

        $env ??= \defined(self::APP_ENV) ? \constant(self::APP_ENV) : null;

        if (! \is_string($env)) {
            return AppEnvironment::Failsafe;
        }

        return AppEnvironment::parse($env);
    }

    /**
     * Resolve {@see $debug}.
     *
     * {@see AppEnvironment::Failsafe} always returns `false`.
     */
    private function resolveDebug(
        null|bool $debug,
    ): bool {
        if ($this->environment === AppEnvironment::Failsafe) {
            return false;
        }

        return $this->resolveBoolFlag($debug, self::APP_DEBUG, default: false);
    }

    /**
     * Resolve {@see $public}.
     *
     * {@see AppEnvironment::Failsafe} always returns `true`.
     */
    private function resolvePublic(
        null|bool $public,
    ): bool {
        if ($this->environment === AppEnvironment::Failsafe) {
            return true;
        }

        return $this->resolveBoolFlag($public, self::APP_PUBLIC, default: false);
    }

    /**
     * Resolve a bool flag: explicit → env → PHP constant → `$default`.
     *
     * Do not use `?:` on env values — `"0"` must remain set (falsy but valid).
     *
     * @param null|bool $value    Explicit override, or `null` to look up `$key`
     * @param string    $key      `$_ENV` / `getenv` / `defined` name ({@see APP_DEBUG}, {@see APP_PUBLIC})
     * @param bool      $default  When neither env nor constant is set
     */
    private function resolveBoolFlag(
        null|bool $value,
        string    $key,
        bool      $default,
    ): bool {
        $value ??= match (true) {
            \array_key_exists($key, $_ENV) => $_ENV[$key],
            false !== ( $env = \getenv($key) ) => $env,
            default => null,
        };

        $value ??= \defined($key) ? \constant($key) : $default;

        if (\is_bool($value)) {
            return $value;
        }

        return (bool) \filter_var($value, \FILTER_VALIDATE_BOOLEAN);
    }
}

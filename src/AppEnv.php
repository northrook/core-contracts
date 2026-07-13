<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Exceptions\RuntimeException;

final class AppEnv
{
    private static null|AppEnv $instance = null;

    public readonly AppEnvironment $environment;

    public readonly bool $debug;

    public function __construct(
        null|string|AppEnvironment $environment = null,
        null|bool $debug = null,
        private readonly bool $selfInstantiated = false,
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

    private function resolveEnv(
        null|string $env,
    ): AppEnvironment {
        $env ??= \is_phpunit()
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

        $debug ??= $_ENV['APP_DEBUG'] ?? \getenv('APP_DEBUG') ?: null;

        $debug ??= defined('APP_DEBUG') ? \APP_DEBUG : false;

        if (is_bool($debug)) {
            return $debug;
        }

        return (bool) \filter_var($debug, \FILTER_VALIDATE_BOOLEAN);
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Context;

use Northrook\Contracts\SystemContext;

enum AppEnv implements SystemContext
{
    /** Live / customer-facing deployment. */
    case Production;

    /** Local or developer-oriented stage. */
    case Development;

    /** Automated test suite stage (PHPUnit, Pest, Codeception, …). */
    case Testing;

    /** Pre-production mirror of live behaviour. */
    case Staging;

    /**
     * Unresolved or unsafe fallback.
     *
     * Used when the stage cannot be determined, or when {@see AppEnv::from()} receives an unknown slug.
     *
     * {@see \Northrook\Context} treats this as worst-case exposure; debug forced off, all secrets redacted etc.
     */
    case Failsafe;

    /**
     * Map a slug (case-insensitive) to a case.
     *
     * Aliases: `prod` → Production, `dev` → Development, `test` → Testing.
     * Anything else — including empty after lowercasing — → {@see self::Failsafe}.
     */
    public static function from(
        string $value,
    ): self {
        return match (\strtolower($value)) {
            'prod', 'production' => self::Production,
            'dev', 'development' => self::Development,
            'test', 'testing'    => self::Testing,
            'staging'            => self::Staging,
            default              => self::Failsafe,
        };
    }

    public static function resolve(
        null|string|self $appEnv = null,
    ): self {
        $resolve = \is_string($appEnv) ? $appEnv : $appEnv?->name;

        $resolve ??= self::isTestRunner()
            ? self::Testing->name
            : null;

        $resolve ??= $_ENV['APP_ENV'] ?? \getenv('APP_ENV') ?: null;

        $resolve ??= \defined('APP_ENV') ? \constant('APP_ENV') : null;

        if (! \is_string($resolve)) {
            return self::Failsafe;
        }

        return AppEnv::from($resolve);
    }

    private static function isTestRunner(): bool
    {
        return \array_any(
            [
                'PHPUNIT_COMPOSER_INSTALL',
                '__PHPUNIT_PHAR__',
                'PEST',
                'CODECEPTION_VERSION',
            ],
            static fn($constant) => \defined($constant),
        );
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Deployment / runtime stage for the process.
 *
 * Resolved by {@see AppEnv} from an explicit value, env, or constant. Unknown
 * slugs map to {@see self::Failsafe} via {@see self::parse()}.
 *
 * This is the application stage — not the SAPI, and not the {@see AppEnv::$public}
 * trust-boundary flag.
 */
enum AppEnvironment: string
{
    /** Live / customer-facing deployment. */
    case Production = 'production';

    /** Local or developer-oriented stage. */
    case Development = 'development';

    /** Automated test suite stage (PHPUnit, Pest, Codeception, …). */
    case Testing = 'testing';

    /** Pre-production mirror of live behaviour. */
    case Staging = 'staging';

    /**
     * Unresolved or unsafe fallback.
     *
     * Used when the stage cannot be determined, or when {@see self::parse()}
     * receives an unknown slug. {@see AppEnv} treats this as worst-case exposure:
     * debug forced off, public forced on.
     */
    case Failsafe = 'failsafe';

    /**
     * Map a slug (case-insensitive) to a case.
     *
     * Aliases: `prod` → Production, `dev` → Development, `test` → Testing.
     * Anything else — including empty after lowercasing — → {@see self::Failsafe}.
     */
    public static function parse(
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
}

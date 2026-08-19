<?php

declare(strict_types=1);

namespace Northrook\Context;

use Northrook\Contracts\SystemContext;

enum AppDebug implements SystemContext
{
    case Enabled;
    case Verbose;
    case Disabled;

    public function isEnabled(
        null|self $resolve = null,
    ): bool {
        return match ($resolve ?? $this) {
            self::Enabled, self::Verbose => true,
            default => false,
        };
    }

    public function isDisabled(): bool
    {
        return $this === self::Disabled;
    }

    public static function resolve(
        null|string|self $appDebug = null,
        null|AppEnv      $appEnv = null,
    ): self {
        $appEnv ??= AppEnv::Failsafe;

        if ($appEnv === AppEnv::Failsafe) {
            return self::Disabled;
        }

        $resolve = \is_string($appDebug)
            ? $appDebug
            : $appDebug?->name;

        $resolve ??= $_ENV['APP_DEBUG'] ?? \getenv('APP_DEBUG') ?: null;

        $resolve ??= \defined('APP_DEBUG') ? \constant('APP_DEBUG') : null;

        if (\is_string($resolve)) {
            $resolve = \strtolower($resolve);
        }

        return match ($resolve) {
            true, 'true', 'enabled', '1', 1    => self::Enabled,
            false, 'false', 'disabled', '0', 0 => self::Disabled,
            'verbose', 'v'                     => self::Verbose,
            default                            => $appEnv === AppEnv::Production
                ? self::Disabled
                : self::Enabled,
        };
    }
}

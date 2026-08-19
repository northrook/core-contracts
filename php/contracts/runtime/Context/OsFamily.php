<?php

declare(strict_types=1);

namespace Northrook\Context;

use Northrook\Contracts\SystemContext;

/**
 * Operating system family of the current PHP runtime.
 */
enum OsFamily implements SystemContext
{
    case Windows;
    case Linux;
    case MacOS;
    case BSD;
    case Solaris;
    case Unknown;

    public static function resolve(
        null|string|self $osFamily = null,
    ): self {
        return $osFamily instanceof self
            ? $osFamily
            : self::from($osFamily ?? \PHP_OS_FAMILY);
    }

    public static function from(
        string $value,
    ): self {
        return match (\strtolower($value)) {
            'windows'                => self::Windows,
            'linux'                  => self::Linux,
            'darwin', 'macos', 'osx' => self::MacOS,
            'bsd'                    => self::BSD,
            'solaris'                => self::Solaris,
            default                  => self::Unknown,
        };
    }

    public static function current(): self
    {
        return self::from(\PHP_OS_FAMILY);
    }

    public function is(
        self ...$family,
    ): bool {
        return \in_array($this, $family, true);
    }

    /**
     * Whether the current Linux runtime appears to be running under WSL.
     */
    public static function isWSL(): bool
    {
        if (self::current() !== self::Linux) {
            return false;
        }

        $path = '/proc/version';

        if (! \is_readable($path)) {
            return false;
        }

        $version = @\file_get_contents($path);

        if ($version === false) {
            return false;
        }

        $version = \strtolower($version);

        return \str_contains($version, 'microsoft') || \str_contains($version, 'wsl');
    }
}

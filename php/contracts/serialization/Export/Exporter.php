<?php

declare(strict_types=1);

namespace Northrook\Export;

use Northrook\Reflect;
use Northrook\RuntimeException;
use Symfony\Component\VarExporter\VarExporter;

enum Exporter: string
{
    case Symfony    = 'symfony/var-exporter';
    case Polyfill   = 'symfony/polyfill-deepclone';
    case Extension  = 'deepclone';
    case Reflection = 'reflection';

    private const string CONTRACTS_PACKAGE = 'northrook/core-contracts';

    public static function resolve(): self
    {
        return match (true) {
            self::hasSymfony() => self::Symfony,
            self::hasExtension() => self::Extension,
            self::hasPolyfill() => self::Polyfill,
            self::hasReflection() => self::Reflection,
            default => throw new RuntimeException(
                message: 'No trusted export backend is available.',
            ),
        };
    }

    public static function hasExtension(): bool
    {
        return \extension_loaded('deepclone') && self::functionsExist();
    }

    public static function hasPolyfill(): bool
    {
        // If composer is not installed, we can't use the polyfill.
        if (! \is_valid_class(\Composer\InstalledVersions::class)) {
            return false;
        }

        $package = Exporter::Polyfill->value;

        // Ensure the `symfony/polyfill-deepclone` package is installed.
        if (\Composer\InstalledVersions::isInstalled($package)) {
            $packagePath = \Composer\InstalledVersions::getInstallPath($package);
            $installPath = $packagePath ? \realpath($packagePath) : false;
        }
        else {
            return false;
        }

        // Ensure the path is valid.
        if ($installPath === false || ! @\is_dir($installPath)) {
            return false;
        }

        // Required functions sanity check.
        if (! self::functionsExist()) {
            return false;
        }

        $toFile = \realpath(
            new \ReflectionFunction('\deepclone_to_array')->getFileName() ?: '',
        );

        $fromFile = \realpath(
            new \ReflectionFunction('\deepclone_from_array')->getFileName() ?: '',
        );

        if ($toFile === false || $fromFile === false) {
            return false;
        }

        $prefix = \rtrim($installPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return \str_starts_with($toFile, $prefix) && \str_starts_with($fromFile, $prefix);
    }

    public static function hasSymfony(): bool
    {
        // If composer is not installed, we can't use the package.
        if (! \is_valid_class(\Composer\InstalledVersions::class)) {
            return false;
        }

        // Ensure the `symfony/var-exporter` package is installed.
        if (! \Composer\InstalledVersions::isInstalled(Exporter::Symfony->value)) {
            return false;
        }

        if (! \is_valid_class(VarExporter::class)) {
            return false;
        }

        $packagePath  = \Composer\InstalledVersions::getInstallPath(Exporter::Symfony->value);
        $installPath  = $packagePath === null ? false : \realpath($packagePath);
        $exporterFile = \realpath(
            new \ReflectionClass(VarExporter::class)->getFileName() ?: '',
        );

        if ($installPath === false || $exporterFile === false || ! @\is_dir($installPath)) {
            return false;
        }

        $prefix = \rtrim($installPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return \str_starts_with($exporterFile, $prefix);
    }

    public static function hasReflection(): bool
    {
        if (! \is_valid_class(\Composer\InstalledVersions::class)) {
            return false;
        }

        $package = self::CONTRACTS_PACKAGE;

        if (! \Composer\InstalledVersions::isInstalled($package)) {
            return false;
        }

        $packagePath = \Composer\InstalledVersions::getInstallPath($package);
        $installPath = $packagePath ? \realpath($packagePath) : false;

        if ($installPath === false || ! @\is_dir($installPath)) {
            return false;
        }

        if (! \function_exists('\instantiate') || ! \is_valid_class(Reflect::class)) {
            return false;
        }

        $instantiateFile = \realpath(
            new \ReflectionFunction('\instantiate')->getFileName() ?: '',
        );
        $reflectFile = \realpath(
            new \ReflectionClass(Reflect::class)->getFileName() ?: '',
        );

        if ($instantiateFile === false || $reflectFile === false) {
            return false;
        }

        $prefix = \rtrim($installPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return \str_starts_with($instantiateFile, $prefix) && \str_starts_with($reflectFile, $prefix);
    }

    private static function functionsExist(): bool
    {
        return \function_exists('\deepclone_to_array') && \function_exists('\deepclone_from_array');
    }
}

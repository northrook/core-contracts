<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Resettable;
use Symfony\Component\VarExporter\VarExporter;

/**
 * Controlled export helpers that temporarily allow trusted serialization of
 * secret-bearing {@see Serializable} graphs in public environments.
 *
 * A single process-wide override is armed for the duration of each top-level
 * call. Nested {@see Serializable} objects inherit it — no per-instance marks.
 * The flag is always cleared in `finally`, including on throw.
 *
 * This is the exclusive outbound bypass for {@see Serializer} credential
 * refusal. Direct {@see serialize()} / {@see json_encode()} / `_export` remain
 * refused under {@see \Northrook\Kernel\KernelContext::Request}.
 */
final class Exporter implements Resettable
{
    /** @var int<0, max> */
    private static int $overrideDepth = 0;

    private function __construct() {}

    public static function serialize(
        mixed $value,
    ): string {
        return self::withOverride(static fn(): string => \serialize($value));
    }

    public static function json(
        mixed $value,
        int   $flags = 0,
        int   $depth = 512,
    ): string|false {
        if ($depth < 1) {
            throw new InvalidArgumentException('JSON encoding depth must be greater than zero.');
        }

        return self::withOverride(
            static fn(): string|false => \json_encode($value, $flags, $depth),
        );
    }

    public static function var(
        mixed $value,
    ): string {
        if (! \class_exists(VarExporter::class)) {
            throw new RuntimeException('symfony/var-exporter is required for Exporter::var().');
        }

        return self::withOverride(static fn(): string => VarExporter::export($value));
    }

    /**
     * Whether a trusted export pass is currently in progress.
     *
     * {@see Serializer::__serialize()} consults this instead of
     * the active {@see \Northrook\Kernel\KernelContext} for credential refusal.
     */
    public static function isOverrideActive(): bool
    {
        return self::$overrideDepth > 0;
    }

    public static function reset(): void
    {
        self::$overrideDepth = 0;
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    private static function withOverride(
        callable $fn,
    ): mixed {
        self::$overrideDepth++;

        try {
            return $fn();
        } finally {
            self::$overrideDepth--;
        }
    }
}

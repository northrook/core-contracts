<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts\Exceptions\RuntimeException;

/**
 * Base class for configuration DTOs created from container-provided arrays.
 *
 * The container bootstrapping phase is expected to resolve the entire config tree
 * into values that match the target constructor signature (enums, nested objects,
 * scalars, etc.).
 *
 * This class simply forwards the provided associative array as named arguments to
 * the concrete constructor and fails fast if the structure or types are incompatible.
 */
abstract readonly class ConfigObject extends DataObject
{
    /**
     * Creates an instance from an associative config array.
     *
     * Keys are forwarded to the constructor as named arguments via `new static(...$config)`.
     * That means:
     * - unknown keys throw
     * - wrong value types throw
     * - missing required parameters throw
     *
     * @param array<non-empty-string, mixed> $config
     *
     * @return static
     *
     * @throws RuntimeException if the config array is invalid or the constructor fails
     */
    final public static function from(
        array $config,
    ): static {
        try {
            // @phpstan-ignore-next-line - Unsafe usage of new static() is intentional
            return new static(...$config);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                message: 'Failed to create ' . static::class . ' from config array.',
                context: \func_get_args(),
                previous: $exception,
            );
        }
    }
}

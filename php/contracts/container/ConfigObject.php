<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Argument\Value;

/**
 * Base class for configuration DTOs created from container-provided arrays.
 *
 * The container bootstrapping phase is expected to resolve the entire config tree
 * into values that match the target constructor signature (enums, nested objects,
 * scalars, etc.).
 *
 * {@see from()} validates `$config` against {@see DEFAULTS}, then forwards the
 * resolved named arguments to the concrete constructor.
 */
abstract readonly class ConfigObject extends DataObject
{
    /**
     * Config schema and defaults for {@see from()}.
     *
     * Keys are the only accepted config keys (allowlist). Values:
     * - {@see Value} — required; **absent** key throws (`array_key_exists`).
     *   {@see Value::Unset} accepts any provided value (including `null`);
     *   typed cases also {@see Value::matches()} the provided value.
     * - other — optional default when the key is absent (`null` is a real default)
     * - callable-string — invoked as `$callable($config)` when the key is absent
     *
     * Must cover every constructor parameter.
     *
     * @abstract
     *
     * @var array<non-empty-string, mixed>
     */
    const array DEFAULTS = [];

    /**
     * Creates an instance from an associative config array.
     *
     * Resolution against {@see DEFAULTS}:
     * 1. Each DEFAULTS key becomes a constructor argument
     * 2. DEFAULTS {@see Value} + absent key → throws (required)
     * 3. DEFAULTS {@see Value} + present key → {@see Value::require()}
     * 4. Absent key + callable-string default → invoke `$callable($config)`
     * 5. Absent key + other default → use that value
     * 6. Keys present in `$config` but not in DEFAULTS → throws (unknown)
     * 7. Remaining type / arity failures come from `new static(...$args)`
     *
     * Presence is {@see array_key_exists()} — explicit `null` values are not treated as absent.
     *
     * All failures are wrapped in a {@see RuntimeException} with the original as
     * `$previous` (including the explicit missing/unknown errors above).
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
            /** @var array<string, mixed> $args */
            $args = [];

            foreach (static::DEFAULTS as $key => $value) {
                $present = \array_key_exists($key, $config);

                if ($value instanceof Value) {
                    if (! $present) {
                        throw new RuntimeException(
                            message: "Missing required config `{$key}`",
                            context: [
                                'config'   => $config,
                                'defaults' => static::DEFAULTS,
                            ],
                        );
                    }

                    $args[$key] = $value->require($config[$key], $key);
                    continue;
                }

                if ($present) {
                    $args[$key] = $config[$key];
                    continue;
                }

                // Callable-string defaults are resolved lazily from the provided config
                if (\is_string($value) && \is_callable($value)) {
                    try {
                        $args[$key] = $value($config);
                    } catch (\Throwable $exception) {
                        throw new RuntimeException(
                            message : "Failed to resolve config `{$key}`",
                            context : [
                                'config'   => $config,
                                'callable' => $value,
                            ],
                            previous: $exception,
                        );
                    }
                    continue;
                }

                $args[$key] = $value;
            }

            // Reject anything not declared in DEFAULTS
            $unknownKeys = \array_diff_key($config, static::DEFAULTS);

            if ($unknownKeys !== []) {
                throw new RuntimeException(
                    message: 'Unknown config keys: ' . \implode(', ', \array_keys($unknownKeys)),
                );
            }

            // @phpstan-ignore-next-line - Unsafe usage of new static() is intentional
            return new static(...$args);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                message : 'Failed to create ' . static::class . ' from config array.',
                context : \func_get_args(),
                previous: $exception,
            );
        }
    }
}

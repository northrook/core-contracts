<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Read-only access to application settings.
 *
 * Calling {@see get()} with an unknown key returns `null`.
 */
interface SettingsInterface
{
    /**
     * @param non-empty-string  $key
     */
    public function has(
        string $key,
    ): bool;

    /**
     * Returns `null` when the key is unknown.
     *
     * @param non-empty-string  $key
     *
     * @return bool|float|int|string|\UnitEnum|null
     */
    public function get(
        string $key,
    ): bool|float|int|string|\UnitEnum|null;

    /**
     * @return array<non-empty-string, bool|float|int|string|\UnitEnum|null>
     */
    public function all(): array;
}

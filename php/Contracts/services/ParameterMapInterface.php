<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Read-only access to named parameters by canonical key (`app.token`).
 *
 * {@see get()} throws {@see NotFoundException} when missing.
 * {@see isSecret()} is false when the key is absent (does not throw).
 *
 * @phpstan-type ParameterValue array<array-key, mixed>|bool|float|int|string|\UnitEnum|null
 */
interface ParameterMapInterface
{
    /**
     * @param non-empty-string  $key
     */
    public function has(
        string $key,
    ): bool;

    /**
     * @param non-empty-string  $key
     *
     * @return ParameterValue
     *
     * @throws NotFoundException
     */
    public function get(
        string $key,
    ): array|bool|float|int|string|\UnitEnum|null;

    /**
     * True when the parameter exists and its secret type exactly matches {@code $type}.
     *
     * @param non-empty-string               $key
     * @param 'sensitive'|'credential'       $type
     */
    public function isSecret(
        string $key,
        string $type = Value\Secret::SENSITIVE,
    ): bool;

    /**
     * @return array<non-empty-string, ParameterValue>
     */
    public function all(): array;
}

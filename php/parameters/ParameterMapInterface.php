<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Read-only access to named parameters by canonical key (`app.token`).
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
     * @return ParameterInterface
     *
     * @throws NotFoundException when `$key` does not exist
     */
    public function get(
        string $key,
    ): ParameterInterface;

    /**
     * @return array<non-empty-string, ParameterInterface>
     */
    public function all(): array;
}

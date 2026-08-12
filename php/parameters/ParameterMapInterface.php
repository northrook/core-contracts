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
     * @return Parameter
     *
     * @throws NotFoundException when `$key` does not exist
     */
    public function get(
        string $key,
    ): Parameter;

    /**
     * @return array<non-empty-string, Parameter>
     */
    public function all(): array;
}

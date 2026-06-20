<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Container;

use LogicException;

/**
 * Mutable store of named container parameters.
 *
 * Extends {@see ParameterMapInterface} with methods to assign, replace,
 * and remove values at runtime.
 */
interface ParameterStoreInterface extends ParameterMapInterface
{
    /**
     * @param array<string, mixed> $parameters
     * @param bool                 $replace
     *
     * @return void
     */
    public function assign(array $parameters, bool $replace = false): void;

    /**
     * Will not override existing parameters.
     *
     * @param non-empty-string $parameter
     * @param mixed            $value
     *
     * @return self
     */
    public function add(string $parameter, mixed $value): self;

    /**
     * Replaces exising parameters.
     *
     * @param non-empty-string $parameter
     * @param mixed            $value
     *
     * @return self
     */
    public function set(string $parameter, mixed $value): self;

    /**
     * Remove one or more parameters by key.
     *
     * @param string ...$parameter
     *
     * @return self
     */
    public function remove(string ...$parameter): self;

    /**
     * Clear all parameters.
     *
     * @throws LogicException if it cannot be cleared
     */
    public function clear(): void;
}

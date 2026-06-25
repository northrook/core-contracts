<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container;

interface ContainerInterface extends \Psr\Container\ContainerInterface
{
    /**
     * @template T of object
     * @param  class-string<T> $id
     * @return T
     */
    public function get(
        string $id,
    ): mixed;

    public function hasParameter(
        string $name,
    ): bool;

    public function getParameter(
        string $name,
    ): Parameter;

    /**
     * @return list<object>
     */
    public function getByRole(
        string $role,
    ): array;

    /**
     * Reset scoped / request-lifetime services (ResetInterface).
     * Called by kernel between requests or CLI commands.
     */
    public function reset(): void;
}

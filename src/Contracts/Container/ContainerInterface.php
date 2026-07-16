<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container;

use Northrook\Contracts\Exceptions\ServiceNotFoundException;

interface ContainerInterface extends \Psr\Container\ContainerInterface
{
    /**
     * ServiceMap key for the primary binding of a service `id`.
     *
     * Passed as `null` to {@see get()}, {@see has()}, and {@see initialized()}.
     *
     * Each `class-string $id` one primary binding, named references select alternates under the same `id`.
     */
    public const string PRIMARY_REFERENCE = 'primary';

    /**
     * Resolve a service instance for the given type and binding.
     *
     * `$reference` selects a named binding under `$id`; `null` uses the primary (canonical) binding.
     *
     * @template T of object
     * @param  class-string<T> $id
     * @param  null|string     $reference binding key, or `null` for `primary`
     * @return T
     *
     * @throws ServiceNotFoundException when the `(id, reference)` pair is not in the compiled container
     */
    public function get(
        string $id,
        null|string $reference = null,
    ): object;

    /**
     * Whether the compiled container defines this `(id, reference)` binding.
     *
     * Does not create the service, only checks that the binding is defined.
     *
     * - `true` means `get()` will not throw {@see ServiceNotFoundException} for a missing binding.
     * - Other errors (e.g. circular dependencies) are still possible.
     *
     * @template T of object
     * @param  class-string<T> $id
     * @param  null|string     $reference binding key, or `null` for `primary`
     */
    public function has(
        string $id,
        null|string $reference = null,
    ): bool;

    /**
     * Whether this binding exists and has already been materialized.
     *
     * `true` only when the instance is initialized.
     *
     * When this returns `true`, {@see get()} returns the same instance.
     *
     * @template T of object
     * @param  class-string<T> $id
     * @param  null|string     $reference binding key, or `null` for `primary`
     *
     * @phpstan-assert-if-true T $this->get()
     */
    public function initialized(
        string $id,
        null|string $reference = null,
    ): bool;

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
     *
     * Called by kernel between requests or CLI commands.
     */
    public function reset(): void;
}

<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Container\ServiceDefinition;

/**
 * Compiled DI container.
 *
 * - {@see self::get()} / {@see self::has()} / {@see self::initialized()} take an
 *   optional `$reference`; `null` selects the primary binding
 *   ({@see self::DEFAULT_REFERENCE}).
 * - Primary constructor/factory overrides for a service live on a reserved
 *   {@see \Northrook\Container\Service\Tag} keyed by {@see self::DEFAULT_REFERENCE}.
 * - Every registered object is a **Service**, identified by its `class-string`.
 */
interface ContainerInterface extends \Psr\Container\ContainerInterface
{
    /**
     * Canonical binding key for the primary instance of a service.
     *
     * Passed as `null` to {@see get()}, {@see has()}, and {@see initialized()}.
     * Also the reserved {@see \Northrook\Container\Service\Tag::$reference} for
     * primary constructor/factory argument overrides on {@see ServiceDefinition}.
     */
    final public const string DEFAULT_REFERENCE = ContainerInterface::class;

    /**
     * Resolve a service instance for the given type and binding.
     *
     * `$reference` selects a named binding under `$id`; `null` uses the primary
     * (canonical) binding ({@see self::DEFAULT_REFERENCE}), including any
     * reserved-tag argument overrides.
     *
     * @template T of object
     * @param  class-string<T> $id
     * @param  null|string     $reference binding key, or `null` for primary binding
     *
     * @return T
     *
     * @throws \Northrook\Container\ServiceNotFoundException if the service is not found
     */
    public function get(
        string      $id,
        null|string $reference = null,
    ): object;

    /**
     * Whether the compiled container defines this `(id, reference)` binding.
     *
     * Does not create the service, only checks that the binding is defined.
     *
     * - {@see self::get()} will not throw {@see \Northrook\Container\ServiceNotFoundException} when this returns `true`.
     * - Other errors (e.g. circular dependencies) are still possible.
     *
     * @template T of object
     * @param  class-string<T> $id
     * @param  null|string     $reference binding key, or `null` for primary binding
     *
     * @return bool `true` if the binding exists, `false` otherwise
     */
    public function has(
        string      $id,
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
     * @param  null|string     $reference binding key, or `null` for primary binding
     *
     * @phpstan-assert-if-true T $this->get()
     */
    public function initialized(
        string      $id,
        null|string $reference = null,
    ): bool;

    public function hasParameter(
        string $key,
    ): bool;

    public function getParameter(
        string $key,
    ): Parameter;
}

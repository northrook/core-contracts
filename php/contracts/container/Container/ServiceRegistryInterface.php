<?php

declare(strict_types=1);

namespace Northrook\Container;

interface ServiceRegistryInterface
{
    /**
     * Whether a definition exists for `(id, reference)`.
     *
     * @param class-string $id may be a service class or an alias
     * @param null|string  $reference selects a named binding under that service
     */
    public function has(
        string      $id,
        null|string $reference = null,
    ): bool;

    /**
     * Definition for `(id, reference)`.
     *
     * @param class-string $id may be a service class or an alias
     * @param null|string  $reference selects a named binding under that service
     *
     * @throws \Northrook\Container\ServiceNotFoundException if the binding is not defined
     */
    public function get(
        string      $id,
        null|string $reference = null,
    ): ServiceDefinition;

    /**
     * Register a definition, indexing its aliases and tags.
     *
     * @param bool $replace when `false`, throws if `$definition->class` is already registered
     *
     * @throws \Northrook\Container\ContainerException on conflict when `$replace` is `false`, or when the phase forbids writes
     */
    public function add(
        ServiceDefinition $definition,
        bool              $replace = false,
    ): ServiceDefinition;

    /**
     * Remove a service (`$reference === null`) or a named binding under it.
     *
     * @param class-string $id may be a service class or an alias
     * @param null|string  $reference selects a named binding under that service
     *
     * @throws \Northrook\Container\ServiceNotFoundException if the binding is not defined
     * @throws \Northrook\Container\ContainerException when the phase forbids writes
     */
    public function remove(
        string      $id,
        null|string $reference = null,
    ): void;
}

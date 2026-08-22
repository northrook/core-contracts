<?php

declare(strict_types=1);

namespace Northrook\Container;

interface ServiceRegistryInterface
{
    /**
     * Whether a definition exists for `(id)`.
     *
     * @param class-string $id may be a service class or an alias
     */
    public function has(
        string $id,
    ): bool;

    /**
     * Retrieve the definition for `(id)`.
     *
     * @param class-string $id may be a service class or an alias
     *
     * @throws \Northrook\Container\ServiceNotFoundException if the binding is not defined
     */
    public function get(
        string $id,
    ): ServiceDefinition;

    /**
     * Register a definition, indexing its aliases and tags.
     *
     * @throws \Northrook\Container\ContainerException on if the definition already exists
     */
    public function register(
        ServiceDefinition $definition,
    ): ServiceDefinition;

    /**
     * Remove a definition.
     *
     * @param class-string $id may be a service class or an alias
     *
     * @throws \Northrook\Container\ServiceNotFoundException if the binding is not defined
     * @throws \Northrook\Container\ContainerException when the phase forbids writes
     */
    public function remove(
        string $id,
    ): void;
}

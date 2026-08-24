<?php

declare(strict_types=1);

namespace Northrook\Container\Compiler;

use Northrook\Container\ServiceDefinition;

interface ServiceRegistryInterface extends CompilerStateInterface
{
    /**
     * Definitions keyed by service id.
     *
     * @var array<non-empty-lowercase-string, ServiceDefinition>
     */
    public array $definitions { get; }

    /**
     * Alias → service id. Does not include the implementing class.
     *
     * @var array<class-string, non-empty-lowercase-string>
     */
    public array $aliases { get; }

    /**
     * Whether a definition exists.
     *
     * @param non-empty-lowercase-string|class-string $id may be a service id, class, or alias
     */
    public function has(
        string $id,
    ): bool;

    /**
     * Retrieve a definition.
     *
     * @param non-empty-lowercase-string|class-string $id may be a service id, class, or alias
     *
     * @throws \Northrook\Container\ServiceNotFoundException if the binding is not defined
     */
    public function get(
        string $id,
    ): ServiceDefinition;

    /**
     * Register a definition, indexing its aliases and tags.
     *
     * @throws \Northrook\Container\ContainerException if the definition already exists or the registry is locked
     */
    public function register(
        ServiceDefinition $definition,
    ): ServiceDefinition;

    /**
     * Remove a definition.
     *
     * @param non-empty-lowercase-string|class-string $id may be a service id, class
     *
     * @throws \Northrook\Container\ContainerException the registry is locked
     *
     * @return bool `true` if the binding was removed, `false` otherwise
     */
    public function remove(
        string $id,
    ): bool;
}

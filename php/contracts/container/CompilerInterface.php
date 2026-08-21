<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Container\CompilerPass;
use Northrook\Container\CompilerPassInterface;
use Northrook\Container\ServiceDefinition;

/**
 * Mutable container builder consumed by {@see CompilerPassInterface} passes.
 *
 * Pipeline:
 * 0. {@see CompilerPass::INITIALIZATION}
 * 1. {@see CompilerPass::DISCOVERY}
 * 2. {@see CompilerPass::PARSE}
 * 3. {@see CompilerPass::OPTIMIZE}
 * 4. {@see CompilerPass::VALIDATE}
 * 5. {@see CompilerPass::COMPILE}
 *
 * Registerable passes target the mutable phases.
 *
 * {@see CompilerPass::COMPILE} freezes into an immutable {@see ContainerInterface}.
 *
 * Per-service mutation (binding, tags, aliases, …) lives on {@see ServiceDefinition}.
 * Primary constructor/factory argument overrides are the reserved
 * {@see \Northrook\Container\Service\Tag} keyed by
 * {@see ContainerInterface::DEFAULT_REFERENCE} (via {@see ServiceDefinition::setArguments()}).
 *
 * This interface is the registry, parameter store, and passes only.
 */
interface CompilerInterface
{
    /**
     * Compiler passes, in order.
     *
     * @var array<int, CompilerPass>
     */
    final public const array PASSES = [
        0 => CompilerPass::INITIALIZATION,
        1 => CompilerPass::DISCOVERY,
        2 => CompilerPass::PARSE,
        3 => CompilerPass::OPTIMIZE,
        4 => CompilerPass::VALIDATE,
        5 => CompilerPass::COMPILE,
    ];

    /**
     * The current compiler pass.
     *
     * @var \Northrook\Container\CompilerPass
     */
    public CompilerPass $pass { get; }

    /**
     * The mutable Parameter Store.
     *
     * @var \Northrook\ParameterStoreInterface
     */
    public ParameterStoreInterface $parameters { get; }

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

    /**
     * All registered definitions, keyed by service class.
     *
     * @return array<class-string, ServiceDefinition>
     */
    public function services(): array;
}

<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\ContainerInterface;
use Northrook\Events\ListenerMapInterface;
use Northrook\ParameterStoreInterface;

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
 * {@see CompilerPass::COMPILE} freezes into an immutable {@see ContainerInterface}
 * and {@see \Northrook\Events\EventListeners}.
 *
 * Per-service mutation (binding, tags, aliases, …) lives on {@see ServiceDefinition}.
 * Primary constructor/factory argument overrides are the reserved
 * {@see \Northrook\Container\Service\Tag} keyed by
 * {@see ContainerInterface::DEFAULT_REFERENCE} (via {@see ServiceDefinition::setArguments()}).
 *
 * This interface is the registry, parameter store, listener map, and passes.
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
     * The compiler's mutable parameter store.
     *
     * @var \Northrook\ParameterStoreInterface
     */
    public ParameterStoreInterface $parameters { get; }

    /**
     * The compiler's mutable service registry.
     *
     * @var \Northrook\Container\ServiceRegistryInterface
     */
    public ServiceRegistryInterface $services { get; }

    /**
     * The compiler's mutable event listener map.
     *
     * @var \Northrook\Events\ListenerMapInterface
     */
    public ListenerMapInterface $listeners { get; }
}

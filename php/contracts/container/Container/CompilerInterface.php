<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\Container\Compiler\DependencyRegistryInterface;
use Northrook\Container\Compiler\ListenerRegistryInterface;
use Northrook\Container\Compiler\ServiceRegistryInterface;
use Northrook\ContainerInterface;
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
 * and {@see \Northrook\Events\ListenerMap}.
 *
 * Per-service mutation (binding, tags, aliases, …) lives on {@see ServiceDefinition}.
 * Primary constructor/factory argument overrides are the reserved
 * {@see \Northrook\Container\Service\Tag} keyed by
 * {@see ContainerInterface::DEFAULT_REFERENCE} (via {@see ServiceDefinition::setArguments()}).
 *
 * This interface is the registry, parameter store, listener registry, and passes.
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
     * Whether the compiler has finished compiling.
     *
     * @var bool
     */
    public bool $compiled { get; }

    /**
     * The compiler's mutable parameter store.
     *
     * @var \Northrook\ParameterStoreInterface
     */
    public ParameterStoreInterface $parameters { get; }

    /**
     * The compiler's mutable service registry.
     *
     * @var \Northrook\Container\Compiler\ServiceRegistryInterface
     */
    public ServiceRegistryInterface $services { get; }

    /**
     * Constructor, method, and member injection plans.
     *
     * @var \Northrook\Container\Compiler\DependencyRegistryInterface
     */
    public DependencyRegistryInterface $dependencies { get; }

    /**
     * The compiler's mutable event listener registry.
     *
     * @var \Northrook\Container\Compiler\ListenerRegistryInterface
     */
    public ListenerRegistryInterface $listeners { get; }

    /**
     * Authorize a class, or a specific method on that class.
     *
     * @param object|class-string|array{0: object|class-string, 1: non-empty-string}  $subject
     */
    public function authorizeMutation(
        object|string|array $subject,
    ): void;
}

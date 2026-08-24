<?php

declare(strict_types=1);

namespace Northrook\Container\Compiler;

use Northrook\Container\DependencyArgument;
use Northrook\Container\DependencyProperty;

/**
 * Compile-time constructor/factory arguments, method arguments, and property dependencies.
 *
 * Written during {@see \Northrook\Container\CompilerPass::PARSE}.
 *
 * Frozen into the container at {@see \Northrook\Container\CompilerPass::COMPILE}.
 */
interface DependencyRegistryInterface extends CompilerStateInterface
{
    /**
     * Constructor or factory argument plans, keyed by implementing class.
     *
     * @var array<class-string, list<DependencyArgument>>
     */
    public array $arguments { get; }

    /**
     * Public instance method argument plans, keyed by class then method name.
     *
     * @var array<class-string, array<non-empty-string, list<DependencyArgument>>>
     */
    public array $methods { get; }

    /**
     * Property dependencies, keyed by implementing class.
     *
     * @var array<class-string, list<DependencyProperty>>
     */
    public array $properties { get; }

    /**
     * Replace plans for `$class`.
     *
     * @param class-string                                       $class
     * @param list<DependencyArgument>                           $arguments
     * @param array<non-empty-string, list<DependencyArgument>>  $methods
     * @param list<DependencyProperty>                           $properties
     */
    public function register(
        string $class,
        array  $arguments,
        array  $methods,
        array  $properties,
    ): void;
}

<?php

declare(strict_types=1);

namespace Northrook\Container;

/**
 * Compile-time autowire plans: constructor/factory slots, method slots, and property members.
 *
 * Written during {@see CompilerPass::PARSE}. Frozen into the container at {@see CompilerPass::COMPILE}.
 *
 * @phpstan-type InjectionSlot array{key: int|string, kind: 'value'|'service'|'parameter'|'resolve'|'default'|'unresolved', handler: mixed}
 * @phpstan-type MemberSlot array{target: 'property'|'method', name: string, kind: 'service'|'parameter'|'resolve', handler: mixed}
 */
interface InjectionRegistryInterface
{
    /**
     * Constructor or factory argument plans, keyed by implementing class.
     *
     * @var array<class-string, list<InjectionSlot>>
     */
    public array $injection { get; }

    /**
     * Public instance method argument plans, keyed by class then method name.
     *
     * @var array<class-string, array<non-empty-string, list<InjectionSlot>>>
     */
    public array $methods { get; }

    /**
     * Property member injections, keyed by implementing class.
     *
     * @var array<class-string, list<MemberSlot>>
     */
    public array $members { get; }

    /**
     * Replace plans for `$class`.
     *
     * @param class-string                                 $class
     * @param list<InjectionSlot>                          $injection
     * @param array<non-empty-string, list<InjectionSlot>> $methods
     * @param list<MemberSlot>                             $members
     */
    public function set(
        string $class,
        array $injection,
        array $methods,
        array $members,
    ): void;
}

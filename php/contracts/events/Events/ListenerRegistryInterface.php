<?php

declare(strict_types=1);

namespace Northrook\Events;

/**
 * Mutable listener registry consumed by {@see \Northrook\Container\CompilerPassInterface} passes.
 *
 * Implementation is owned by the compiler. {@see \Northrook\Container\CompilerPass::COMPILE}
 * freezes this map into an immutable {@see \Northrook\Events\ListenerMap}.
 */
interface ListenerRegistryInterface
{
    /**
     * Register a listener for `$event`.
     *
     * @param class-string<\Northrook\EventInterface> $event
     * @param class-string                 $class
     * @param non-empty-string             $method
     */
    public function register(
        string $event,
        string $class,
        string $method,
        int    $priority = 0,
    ): self;

    /**
     * Descriptors whose registered event type is a parent of, or the same as,
     * `$event` ({@see \is_a()}), appended in registration order —
     * no specificity or priority sort.
     *
     * @param \Northrook\EventInterface|class-string<\Northrook\EventInterface> $event
     *
     * @return list<\Northrook\Events\ListenerDescriptor>
     */
    public function for(
        object|string $event,
    ): array;

    /**
     * Whether any listener is registered for the event type (including parents).
     *
     * @param \Northrook\EventInterface|class-string<\Northrook\EventInterface> $event
     */
    public function has(
        object|string $event,
    ): bool;

    /**
     * Freeze this registry into an immutable {@see ListenerMap}.
     */
    public function toListenerMap(): ListenerMap;
}

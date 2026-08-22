<?php

declare(strict_types=1);

namespace Northrook\Events;

use Northrook\EventInterface;

/**
 * Compiled snapshot of event listeners.
 *
 * Source of truth is the container compile; this map is a read model consumed by
 * a {@see \Northrook\ListenerProviderInterface} implementation.
 *
 * Descriptors are `{class, method, priority}`; resolution of `class::method` to an
 * invokable happens in the kernel/container, not here.
 *
 * @phpstan-type ListenerDescriptor array{class: class-string, method: string, priority: int}
 * @phpstan-type ListenerMapInput array<class-string<EventInterface>, list<ListenerDescriptor>>
 */
interface ListenerMapInterface
{
    /**
     * Register a listener for `$event`.
     *
     * @param class-string<EventInterface> $event
     * @param class-string                 $class
     * @param non-empty-string             $method
     */
    public function add(
        string $event,
        string $class,
        string $method,
        int    $priority = 0,
    ): static;

    /**
     * Descriptors whose registered event type is a parent of, or the same as,
     * `$event` ({@see \is_a()}), appended in registration key order —
     * no specificity or priority sort.
     *
     * @param object|class-string $event
     *
     * @return list<ListenerDescriptor>
     */
    public function for(
        object|string $event,
    ): array;

    /**
     * Descriptors for the event, sorted by priority descending.
     *
     * @param object|class-string $event
     *
     * @return list<ListenerDescriptor>
     */
    public function sorted(
        object|string $event,
    ): array;

    /**
     * Whether any listener is registered for the event type (including parents).
     *
     * @param object|class-string $event
     */
    public function has(
        object|string $event,
    ): bool;
}

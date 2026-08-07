<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Immutable compiled snapshot of event listeners.
 *
 * Source of truth is the container compile; this map is a read model consumed by
 * a {@see ListenerProviderInterface} implementation.
 *
 * Descriptors are `{class, method, priority}`; resolution of `class::method` to an
 * invokable happens in the kernel/container, not here.
 *
 * @phpstan-type ListenerDescriptor array{class: class-string, method: string, priority: int}
 * @phpstan-type ListenerMapInput array<class-string<EventInterface>, list<ListenerDescriptor>>
 */
final class ListenerMap
{
    /** @var ListenerMapInput */
    private readonly array $listeners;

    /**
     * @param ListenerMapInput $listeners
     */
    public function __construct(
        array $listeners = [],
    ) {
        $this->listeners = $listeners;
    }

    /**
     * Returns a new map with the listener added (immutable-style).
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
    ): static {
        $listeners           = $this->listeners;
        $listeners[$event][] = [
            'class'    => $class,
            'method'   => $method,
            'priority' => $priority,
        ];

        return new static($listeners);
    }

    /**
     * All listener descriptors, keyed by event class.
     *
     * @return ListenerMapInput
     */
    public function all(): array
    {
        return $this->listeners;
    }

    /**
     * Descriptors whose registered event type is a parent of, or the same as,
     * `$event` ({@see \is_a()}), appended in `$this->listeners` key order —
     * no specificity or priority sort.
     *
     * @param object|class-string $event
     *
     * @return list<ListenerDescriptor>
     */
    public function for(
        object|string $event,
    ): array {
        $class = \is_object($event) ? $event::class : $event;

        $result = [];

        foreach ($this->listeners as $registered => $descriptors) {
            if (\is_a($class, $registered, true)) {
                \array_push($result, ...$descriptors);
            }
        }

        return $result;
    }

    /**
     * Descriptors for the event, sorted by priority descending.
     *
     * @param object|class-string $event
     *
     * @return list<ListenerDescriptor>
     */
    public function sorted(
        object|string $event,
    ): array {
        $descriptors = $this->for($event);

        \usort(
            $descriptors,
            static fn(array $a, array $b): int => $b['priority'] <=> $a['priority'],
        );

        return $descriptors;
    }

    /**
     * Whether any listener is registered for the event type (including parents).
     *
     * @param object|class-string $event
     */
    public function has(
        object|string $event,
    ): bool {
        return $this->for($event) !== [];
    }
}

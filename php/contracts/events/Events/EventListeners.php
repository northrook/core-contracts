<?php

declare(strict_types=1);

namespace Northrook\Events;

/**
 * Immutable, finalized listener map for the container COMPILE phase.
 *
 * Typically produced by the compiler from {@see ListenerMapInterface}.
 *
 * Resolution of {@see ListenerDescriptor::$class}::{@see ListenerDescriptor::$method}
 * to an invokable happens in the kernel/container, not here.
 */
final readonly class EventListeners
{
    /**
     * @param list<ListenerDescriptor>  $listeners
     */
    public function __construct(
        public array $listeners = [],
    ) {}

    /**
     * Descriptors whose registered event type is a parent of, or the same as,
     * `$event` ({@see \is_a()}), appended in `$this->listeners` order —
     * no specificity or priority sort.
     *
     * @param \Northrook\EventInterface|class-string<\Northrook\EventInterface> $event
     *
     * @return list<ListenerDescriptor>
     */
    public function for(
        object|string $event,
    ): array {
        $class = \is_object($event) ? $event::class : $event;

        $result = [];

        foreach ($this->listeners as $descriptor) {
            if (\is_a($class, $descriptor->event, true)) {
                $result[] = $descriptor;
            }
        }

        return $result;
    }

    /**
     * Descriptors for the event, sorted by priority descending.
     *
     * @param \Northrook\EventInterface|class-string<\Northrook\EventInterface> $event
     *
     * @return list<ListenerDescriptor>
     */
    public function sorted(
        object|string $event,
    ): array {
        $descriptors = $this->for($event);

        \usort(
            $descriptors,
            static fn(ListenerDescriptor $a, ListenerDescriptor $b): int => $b->priority <=> $a->priority,
        );

        return $descriptors;
    }

    /**
     * Whether any listener is registered for the event type (including parents).
     *
     * @param \Northrook\EventInterface|class-string<\Northrook\EventInterface> $event
     */
    public function has(
        object|string $event,
    ): bool {
        return $this->for($event) !== [];
    }
}

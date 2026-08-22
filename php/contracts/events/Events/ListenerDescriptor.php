<?php

declare(strict_types=1);

namespace Northrook\Events;

/**
 * A compiled listener binding.
 */
final readonly class ListenerDescriptor
{
    /**
     * @param class-string<\Northrook\EventInterface> $event
     * @param class-string                 $class
     * @param non-empty-string             $method
     * @param int                          $priority
     */
    public function __construct(
        public string $event,
        public string $class,
        public string $method,
        public int    $priority = 0,
    ) {}
}

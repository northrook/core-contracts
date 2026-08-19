<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Default stoppable kernel event.
 *
 * Apps extend this for concrete events.
 *
 * Alternate {@see EventInterface} implementations remain valid subscribe targets.
 */
abstract class Event implements EventInterface
{
    private bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}

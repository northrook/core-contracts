<?php

namespace Core\Contracts\Kernel;

use Psr\EventDispatcher\StoppableEventInterface;

// ?? Way to track previous actions on event?

interface EventInterface extends StoppableEventInterface
{
    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    public function stopPropagation() : void;
}

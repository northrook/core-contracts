<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Kernel;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base contract for kernel events dispatched through the event bus.
 *
 * Extends PSR-14 stoppable events so listeners can halt further propagation.
 */
interface EventInterface extends StoppableEventInterface
{
    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    public function stopPropagation(): void;
}

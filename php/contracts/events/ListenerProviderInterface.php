<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Resolves listeners for an event.
 *
 * PSR-14 port. Yields callables ready to invoke in priority order (higher first);
 * descriptor ({@see \Northrook\Events\ListenerDescriptor}) → service instance
 * wiring happens in the kernel/container, not here.
 *
 * Stop-propagation checks belong to the dispatcher: when
 * {@see \Psr\EventDispatcher\StoppableEventInterface::isPropagationStopped()} is
 * `true`, the dispatcher must not invoke remaining listeners.
 */
interface ListenerProviderInterface extends \Psr\EventDispatcher\ListenerProviderInterface
{
    /**
     * @param object $event
     *
     * @return iterable<callable>
     */
    public function getListenersForEvent(
        object $event,
    ): iterable;
}

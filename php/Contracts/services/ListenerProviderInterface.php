<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;

/**
 * Resolves listeners for an event.
 *
 * PSR-14 port. Yields callables ready to invoke in priority order (higher first);
 * descriptor → service instance wiring happens in the kernel/container, not here.
 *
 * Stop-propagation checks belong to the dispatcher: when
 * {@see \Psr\EventDispatcher\StoppableEventInterface::isPropagationStopped()} is
 * `true`, the dispatcher must not invoke remaining listeners.
 */
interface ListenerProviderInterface extends PsrListenerProviderInterface
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

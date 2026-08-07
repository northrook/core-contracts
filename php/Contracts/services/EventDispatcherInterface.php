<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;

/**
 * Dispatches a kernel event to registered listeners.
 *
 * PSR-14 port. Listeners execute in priority order (higher first). The dispatcher
 * must stop invoking remaining listeners once
 * {@see \Psr\EventDispatcher\StoppableEventInterface::isPropagationStopped()} is
 * `true`.
 *
 * Returns the same event instance passed in.
 */
interface EventDispatcherInterface extends PsrEventDispatcherInterface
{
    /**
     * @template T of object
     *
     * @param T $event
     *
     * @return T
     */
    public function dispatch(
        object $event,
    ): object;
}

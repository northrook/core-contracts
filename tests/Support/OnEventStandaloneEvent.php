<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\EventInterface;

/**
 * Standalone {@see EventInterface} implementation (not extending {@see \Northrook\Event}).
 */
final class OnEventStandaloneEvent implements EventInterface
{
    private bool $stopped = false;

    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}

<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Profiler;

/**
 * A single timed interval recorded by {@see ProfilerInterface}.
 */
abstract class ProfilerEvent
{
    protected readonly float $createdAt;

    /**
     * @internal called by the {@see ProfilerInterface}
     *
     * @param string $name
     * @param string $category
     */
    public function __construct(
        public readonly string $name,
        public readonly string $category,
    ) {
        $this->createdAt = \microtime(true);
    }

    abstract public function start(null|string $note = null): static;

    abstract public function stop(null|string $note = null): static;

    abstract public function snapshot(null|string $note = null): static;
}

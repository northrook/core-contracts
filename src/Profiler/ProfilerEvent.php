<?php

declare(strict_types=1);

namespace Core\Contracts\Profiler;

/**
 * @used-by ProfilerInterface
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
        $this->createdAt = \microtime( true );
    }

    abstract public function start( ?string $note = null ) : static;

    abstract public function stop( ?string $note = null ) : static;

    abstract public function snapshot( ?string $note = null ) : static;
}

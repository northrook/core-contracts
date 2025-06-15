<?php

declare(strict_types=1);

namespace Core\Contracts\Profiler;

abstract class Event
{
    public readonly string $name;

    public function __construct(
        ?string $name = null,
    ) {
        $this->name = $name ?? $this::class;
    }

    abstract public function start() : static;

    abstract public function stop() : static;

    abstract public function lap() : static;
}

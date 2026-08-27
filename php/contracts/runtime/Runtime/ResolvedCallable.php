<?php

declare(strict_types=1);

namespace Northrook\Runtime;

final readonly class ResolvedCallable
{
    /**
     * @param \Closure $callable
     * @param array<array-key, mixed> $arguments
     */
    public function __construct(
        public \Closure $callable,
        public array    $arguments,
    ) {}

    public function invoke(): mixed
    {
        return ( $this->callable )(...$this->arguments);
    }
}

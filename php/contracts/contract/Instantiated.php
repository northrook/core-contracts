<?php

declare(strict_types=1);

namespace Northrook;

final readonly class Instantiated
{
    public function __construct(
        public string $file,
        public int    $line,
        public int    $timestamp,
    ) {}
}

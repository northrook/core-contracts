<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

/**
 * Resets a service to its initial state between requests.
 *
 * Prefer stateless services where possible; implement this when a scoped or
 * pooled instance must clear internal buffers before reuse.
 */
interface ResetInterface
{
    public function reset(): self;
}

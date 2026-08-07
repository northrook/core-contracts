<?php

declare(strict_types=1);

namespace Northrook\Contracts\Service;

use Northrook\Contracts\Container\BindingAttribute;

/**
 * An inline `service`.
 *
 * These are generated on-demand, and aren't retained by the container.
 *
 * Incompatible with {@see Shared}, {@see Unique}, and {@see Scoped}.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Inline implements BindingAttribute
{
    public function __construct(
        public bool $locked = false,
    ) {}
}

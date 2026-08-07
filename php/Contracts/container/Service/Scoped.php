<?php

declare(strict_types=1);

namespace Northrook\Contracts\Service;

use Northrook\Contracts\Container\BindingAttribute;

/**
 * A scoped `service`.
 *
 * These are retained per consuming class.
 *
 * Within a given consumer `FQCN`, repeated injections resolve to the same instance.
 * Outside that consumer, a new instance is generated.
 *
 * Incompatible with {@see Shared}, {@see Unique}, and {@see Inline}.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Scoped implements BindingAttribute
{
    public function __construct(
        public bool $locked = false,
    ) {}
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Service;

use Northrook\Contracts\Container\BindingAttribute;

/**
 * A shared `service`.
 *
 * These are retained in the container.
 *
 * - If {@see \Northrook\Contracts\ContainerInterface::get()} is without a `reference`, the primary `service` is returned.
 * - If {@see \Northrook\Contracts\ContainerInterface::get()} is with a `reference`, a retrievable `service` is returned.
 *
 * Repeated calls to the same `reference` will return the same instance.
 *
 * Incompatible with {@see Inline}, {@see Unique}, and {@see Scoped}.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Shared implements BindingAttribute
{
    public function __construct(
        public bool $locked = false,
    ) {}
}

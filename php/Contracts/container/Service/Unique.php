<?php

declare(strict_types=1);

namespace Northrook\Contracts\Service;

use Northrook\Contracts\Container\BindingAttribute;

/**
 * A unique `service`.
 *
 * These are locked to the container primary reference.
 *
 * If {@see \Northrook\Contracts\ContainerInterface::get()} is called with a `reference` on a `unique`, an {@see \Northrook\Contracts\ContainerException} will be thrown.
 *
 * Incompatible with {@see Shared}, {@see Inline}, and {@see Scoped}.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Unique implements BindingAttribute
{
    public function __construct(
        public bool $locked = false,
    ) {}
}

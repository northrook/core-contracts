<?php

declare(strict_types=1);

namespace Northrook\Container\Service;

use Northrook\Container\BindingAttribute;

/**
 * A unique `service`.
 *
 * These are locked to the container primary reference.
 *
 * If {@see \Northrook\ContainerInterface::get()} or {@see \Northrook\ContainerInterface::has()} is called with a non-default `reference` on a `unique`, resolution fails ({@see \Northrook\Container\ServiceNotFoundException} on {@see \Northrook\ContainerInterface::get()}).
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

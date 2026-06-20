<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Container\Service;

use Attribute;
use Northrook\Contracts\Container\Autodiscover;

/**
 * Registers one or more lookup aliases for a service.
 *
 * @extends Autodiscover<object>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Alias extends Autodiscover
{
    /**
     * - Set one or more aliases for the service.
     *
     * @param string ...$alias
     */
    public function __construct(string ...$alias)
    {
        parent::__construct(alias: \array_values($alias));
    }
}

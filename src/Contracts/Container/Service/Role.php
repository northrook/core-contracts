<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Container\Service;

use Attribute;
use Northrook\Contracts\Container\Autodiscover;

/**
 * Tags a service with one or more container roles.
 *
 * Roles group services for collection by the compiler — for example, event
 * listeners, middleware, or config providers.
 *
 * @extends Autodiscover<object>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Role extends Autodiscover
{
    /**
     * @param array<string, string>|string ...$role
     */
    public function __construct(array|string ...$role)
    {
        parent::__construct(role: $role);
    }
}

<?php

declare(strict_types=1);

namespace Core\Contracts\Container\Service;

use Attribute;
use Core\Contracts\Container\Autodiscover;

/**
 * @extends Autodiscover<object>
 */
#[Attribute( Attribute::TARGET_CLASS )]
final class Role extends Autodiscover
{
    /**
     *  Tag this service with one or more roles, with optional arguments.
     *  ```
     *  role : [
     *    'tagged.role' => [ .. arguments ]
     *  ]
     *
     * @param array<string, string>|string ...$role , $role
     */
    public function __construct( array|string ...$role )
    {
        parent::__construct( role : $role );
    }
}

<?php

declare(strict_types=1);

namespace Core\Contracts\Container\Service;

use Attribute;
use Core\Contracts\Container\Autodiscover;

/**
 * @extends Autodiscover<object>
 */
#[Attribute( Attribute::TARGET_CLASS )]
final class Alias extends Autodiscover
{
    /**
     * @param bool|class-string|string ...$alias
     */
    public function __construct( bool|string ...$alias )
    {
        parent::__construct( alias : \array_values( $alias ) );
    }
}

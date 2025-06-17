<?php

declare(strict_types=1);

namespace Core\Contracts\Container\Service;

use Attribute;
use Core\Contracts\Container\Autodiscover;

/**
 * How to handle instantiation.
 * - `container` provides a singleton, shared instance
 * - `service` provides a new instance per service
 * - `clone` instantiates a new independent object every time
 *
 * @extends Autodiscover<object>
 */
#[Attribute( Attribute::TARGET_CLASS )]
final class Scope extends Autodiscover
{
    /** `Singleton`, shared instance for the `container` */
    public const string CONTAINER = 'container';

    /** Provides a new instance per `service` */
    public const string SERVICE = 'service';

    /** Instantiates a new independent object every time */
    public const string CLONE = 'clone';

    /**
     * @param Scope::* $scope
     */
    public function __construct( string $scope )
    {
        parent::__construct( scope : $scope );
    }
}

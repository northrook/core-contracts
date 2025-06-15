<?php

declare(strict_types=1);

namespace Core\Contracts\Container\Service;

use Attribute;
use Core\Contracts\Container\Autodiscover;
use LogicException;

/**
 * @extends Autodiscover<object>
 */
#[Attribute( Attribute::TARGET_CLASS )]
final class Action extends Autodiscover
{
    protected function configure() : void
    {
        if ( ! $this->getReflectionClass()->hasMethod( '__invoke' ) ) {
            throw new LogicException(
                $this::class." cannot autodiscover class '{$this->class}',\n"
                    ." actions must implement the '__invoke' method.",
            );
        }
    }
}

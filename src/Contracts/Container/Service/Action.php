<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container\Service;

use Attribute;
use LogicException;
use Northrook\Contracts\Container\Autodiscover;

/**
 * Registers an invokable class as a container action.
 *
 * The target class must define {@see __invoke}; registration fails otherwise.
 *
 * @extends Autodiscover<object>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Action extends Autodiscover
{
    protected function configure(): void
    {
        if (! $this->getReflectionClass()->hasMethod('__invoke')) {
            throw new LogicException(
                $this::class
                . " cannot autodiscover class '{$this->class}',\n"
                . " actions must implement the '__invoke' method.",
            );
        }
    }
}

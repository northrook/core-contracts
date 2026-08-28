<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Runtime\ResolverInterface;
use Northrook\Runtime\RunnerInterface;

interface RuntimeInterface
{
    public private(set) RuntimeOptions $options { get; }

    public function getRunner(
        null|object $application,
    ): RunnerInterface;

    public function getResolver(
        callable                 $callable,
        null|\ReflectionFunction $reflector = null,
    ): ResolverInterface;
}

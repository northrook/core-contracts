<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Runtime\ResolverInterface;
use Northrook\Runtime\RunnerInterface;

interface RuntimeInterface
{
    public RuntimeOptions $options { get; }

    public function getRunner(
        object $application,
    ): RunnerInterface;

    public function getResolver(
        callable                 $callable,
        null|\ReflectionFunction $reflector = null,
    ): ResolverInterface;

    /**
     * @param mixed $status
     *
     * @return int<0,254>
     */
    public static function validateExitCode(
        mixed $status,
    ): int;
}

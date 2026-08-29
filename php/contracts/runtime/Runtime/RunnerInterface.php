<?php

declare(strict_types=1);

namespace Northrook\Runtime;

interface RunnerInterface
{
    /**
     * Exit Code from the application runner.
     *
     * Valdiated through {@see \Northrook\RuntimeInterface::validateExitCode()}.
     *
     * @return int<0,254>
     */
    public function run(): int;
}

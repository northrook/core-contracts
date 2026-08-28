<?php

declare(strict_types=1);

namespace Northrook\Runtime;

interface RunnerInterface
{
    /**
     * Exit status from the application runner.
     *
     * The generated runtime bootstrap validates int<0,254> before calling exit().
     */
    public function run(): int;
}

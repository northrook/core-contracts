<?php

namespace Northrook;

use Northrook\Runtime\RunnerInterface;

interface RuntimeInterface
{
    public private(set) RuntimeOptions $options { get; }

    public function getRunner(
        null|object $application,
    ): RunnerInterface;
}

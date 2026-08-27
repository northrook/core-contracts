<?php

declare(strict_types=1);

namespace Northrook\Runtime;

interface RunnerInterface
{
    public function run(): int;
}

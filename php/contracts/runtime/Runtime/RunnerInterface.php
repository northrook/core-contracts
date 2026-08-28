<?php

declare(strict_types=1);

namespace Northrook\Runtime;

interface RunnerInterface
{
    /**
     * @return int<0,254>
     */
    public function run(): int;
}

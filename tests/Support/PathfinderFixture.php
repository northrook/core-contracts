<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Autowire\Pathfinder;
use Northrook\PathfinderInterface;

final class PathfinderFixture
{
    use Pathfinder;

    public function pathfinderIsSet(): bool
    {
        return isset($this->pathfinder);
    }

    public function pathfinderInstance(): PathfinderInterface
    {
        return $this->pathfinder;
    }
}

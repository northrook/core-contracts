<?php

declare(strict_types=1);

namespace Northrook\Autowire;

use Northrook\Container\Autowire;
use Northrook\PathfinderInterface;

/**
 * Autowires the container {@see PathfinderInterface} into {@see static::$pathfinder}.
 */
trait Pathfinder
{
    protected PathfinderInterface $pathfinder;

    /**
     * @param PathfinderInterface $pathfinder
     *
     * @return void
     */
    final public function __autowirePathfinder(
        #[Autowire(PathfinderInterface::class)]
        PathfinderInterface $pathfinder,
    ): void {
        $this->pathfinder = $pathfinder;
    }
}

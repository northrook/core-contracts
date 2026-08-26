<?php

declare(strict_types=1);

namespace Northrook\Autowire;

use Northrook\Container\Autowire;
use Northrook\PathfinderInterface;

/**
 * {@see Autowire} the container {@see PathfinderInterface} into {@see static::$pathfinder}.
 */
trait Pathfinder
{
    protected PathfinderInterface $pathfinder;

    /**
     * @param PathfinderInterface $pathfinder
     *
     * @return void
     */
    final public function _autowire_Pathfinder(
        #[Autowire(PathfinderInterface::class)]
        PathfinderInterface $pathfinder,
    ): void {
        $this->pathfinder = $pathfinder;
    }
}

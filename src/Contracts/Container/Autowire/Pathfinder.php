<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Container\Autowire;

use Northrook\Contracts\Container\Autowire;
use Northrook\Contracts\Interfaces\PathfinderInterface;

/**
 * Autowires the container {@see PathfinderInterface} into {@see static::$pathfinder}.
 */
trait Pathfinder
{
    protected PathfinderInterface $pathfinder;

    /**
     * @internal autowired by the {@see ContainerInterface}
     *
     * @param PathfinderInterface $pathfinder
     *
     * @return void
     *
     * @final
     */
    #[Autowire]
    final public function assignPathfinder(PathfinderInterface $pathfinder): void
    {
        $this->pathfinder = $pathfinder;
    }
}

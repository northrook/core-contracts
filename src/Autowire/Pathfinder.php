<?php

declare(strict_types=1);

namespace Core\Contracts\Autowire;

use Core\Contracts\Container\Autowire;
use Core\Contracts\PathfinderInterface;

trait Pathfinder
{
    protected readonly PathfinderInterface $pathfinder;

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
    final public function assignPathfinder( PathfinderInterface $pathfinder ) : void
    {
        $this->pathfinder = $pathfinder;
    }
}

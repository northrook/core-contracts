<?php

declare(strict_types=1);

namespace Core\Contracts\Autowire;

use Core\Contracts\Container\Autowire;
use Core\Contracts\{ProfilerInterface};
trait Profiler
{
    protected readonly ProfilerInterface $profiler;

    /**
     * @internal autowired by the {@see ContainerInterface}
     *
     * @param ProfilerInterface $profiler
     *
     * @return void
     *
     * @final
     */
    #[Autowire]
    final public function assignProfiler( ProfilerInterface $profiler ) : void
    {
        $this->profiler = $profiler;
    }
}

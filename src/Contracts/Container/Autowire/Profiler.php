<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container\Autowire;

use Northrook\Contracts\Container\Autowire;
use Northrook\Contracts\Interfaces\ProfilerInterface;

/**
 * Autowires the container {@see ProfilerInterface} into {@see static::$profiler}.
 */
trait Profiler
{
    protected ProfilerInterface $profiler;

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
    final public function assignProfiler(
        ProfilerInterface $profiler,
    ): void {
        $this->profiler = $profiler;
    }
}

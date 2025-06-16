<?php

namespace Core\Contracts\Container\Compiler;

use Core\Contracts\Container\CompilerInterface;

interface CompilerPassInterface
{
    /**
     * Processes the given compiler instance.
     *
     * @internal called when compiling the {@see ContainerInterface}
     *
     * @param CompilerInterface $compiler
     *
     * @return void
     */
    public function process( CompilerInterface $compiler ) : void;
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container;

use Northrook\Contracts\CompilerInterface;

/**
 * Handles modifications to the container during the compilation process.
 */
interface CompilerPassInterface
{
    /**
     * Use the {@see CompilerInterface::$pass} to modify the container during a given pass.
     *
     * The {@see \Northrook\Contracts\Priority} attribute can be used to control the order in which passes are executed.
     *
     * @param \Northrook\Contracts\CompilerInterface $compiler
     *
     * @return void
     */
    public function process(
        CompilerInterface $compiler,
    ): void;
}

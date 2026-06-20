<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Container;

interface CompilerPassInterface
{
    public function process(CompilerInterface $compiler): void;
}

<?php

declare(strict_types=1);

namespace Northrook\Runtime;

interface ResolverInterface
{
    public function resolve(): ResolvedCallable;
}

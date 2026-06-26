<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Contracts\Container\Autodiscover;

final class InvalidAutodiscoverCalls
{
    public static function invalidScope(): void
    {
        new Autodiscover(scope: 'invalid');
    }
}

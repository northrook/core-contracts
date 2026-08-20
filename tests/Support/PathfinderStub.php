<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Filesystem\Path;
use Northrook\PathfinderInterface;
use Northrook\Reference\Href;

final class PathfinderStub implements PathfinderInterface
{
    public function getPath(
        string|\Stringable $reference,
    ): null|Path {
        return null;
    }

    public function getHref(
        string|\Stringable $reference,
    ): null|Href {
        return null;
    }
}

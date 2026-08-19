<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Filesystem\Path;
use Northrook\PathfinderInterface;
use Northrook\Url;

final class PathfinderStub implements PathfinderInterface
{
    public function getPath(
        string|\Stringable $reference,
    ): null|Path {
        return null;
    }

    public function getUrl(
        string|\Stringable $reference,
    ): null|Url {
        return null;
    }
}

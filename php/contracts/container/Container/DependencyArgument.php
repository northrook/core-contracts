<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\Contracts\Exportable;
use Northrook\Export;
use Northrook\Serializer;

/**
 * A compiled constructor or method argument injection.
 */
final readonly class DependencyArgument implements Exportable
{
    use Serializer;

    public function __construct(
        public int|string     $key,
        public DependencyType $type,
        public mixed          $handler,
    ) {}

    public function _export(): string
    {
        $this->guardExport();

        return Export::class(
            self::class,
            $this->key,
            $this->type,
            $this->handler,
        );
    }
}

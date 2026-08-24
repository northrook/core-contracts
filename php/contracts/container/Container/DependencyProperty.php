<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\Contracts\Exportable;
use Northrook\Export;
use Northrook\Serializer;

/**
 * A compiled property injection.
 */
final readonly class DependencyProperty implements Exportable
{
    use Serializer;

    public function __construct(
        public string         $name,
        public DependencyType $type,
        public mixed          $handler,
    ) {}

    public function _export(): string
    {
        $this->guardExport();

        return Export::class(
            self::class,
            $this->name,
            $this->type,
            $this->handler,
        );
    }
}

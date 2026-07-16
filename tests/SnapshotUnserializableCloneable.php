<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

/**
 * @internal
 */
final class SnapshotUnserializableCloneable
{
    public function __construct(
        public string $label = 'ok',
        public mixed $callback = null,
    ) {
        $this->callback ??= static fn(): int => 1;
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

/**
 * @internal
 */
final class SnapshotUncopyable
{
    public function __serialize(): array
    {
        throw new \RuntimeException('Serialization intentionally disabled.');
    }

    private function __clone(): void {}
}

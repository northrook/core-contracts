<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Mutable walk limits for a single {@see Snapshot} operation.
 *
 * @internal
 */
final class SnapshotBudget
{
    /** @var int<0, max> */
    private int $nodes = 0;

    /**
     * @param int<1, max> $maxDepth
     * @param int<1, max> $maxNodes
     */
    public function __construct(
        public readonly int $maxDepth,
        public readonly int $maxNodes,
    ) {}

    public function consume(): bool
    {
        if ($this->nodes >= $this->maxNodes) {
            return false;
        }

        $this->nodes++;

        return true;
    }

    public function hasNodesRemaining(): bool
    {
        return $this->nodes < $this->maxNodes;
    }
}

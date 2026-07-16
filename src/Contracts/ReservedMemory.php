<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts\Exceptions\RuntimeException;

/**
 * Disposable memory cushion for fatal / OOM shutdown work.
 */
final class ReservedMemory
{
    private null|string $held = null;

    /**
     * Configures the cushion size; does not allocate until {@see reserve()}.
     *
     * @param int $bytes Slab size in bytes `1`–`16_777_216`
     *
     * @throws RuntimeException when `$bytes` is outside the allowed range
     */
    public function __construct(
        private readonly int $bytes,
    ) {
        if ($bytes < 1) {
            throw new RuntimeException(
                message: 'Reserved memory must be at least 1 byte.',
                context: ['bytes' => $bytes],
            );
        }

        if ($bytes > 16_777_216) {
            throw new RuntimeException(
                message: 'Reserved memory cannot exceed 16 MB.',
                context: ['bytes' => $bytes],
            );
        }
    }

    /**
     * Allocates the null-filled slab if it is not already held.
     */
    public function reserve(): void
    {
        $this->held ??= \str_repeat("\0", $this->bytes);
    }

    /**
     * Drops the slab so subsequent shutdown work can allocate.
     */
    public function release(): void
    {
        $this->held = null;
    }

    /**
     * Whether the cushion is currently allocated.
     */
    public function isReserved(): bool
    {
        return $this->held !== null;
    }

    /**
     * Configured slab size in bytes (allocated or not).
     */
    public function bytes(): int
    {
        return $this->bytes;
    }
}

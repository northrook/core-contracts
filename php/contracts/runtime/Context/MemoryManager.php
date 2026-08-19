<?php

declare(strict_types=1);

namespace Northrook\Context;

use Northrook\Contracts\Resettable;
use Northrook\Runtime\ReservedMemory;

final class MemoryManager implements Resettable
{
    /**
     * @var array<string, \Northrook\Runtime\ReservedMemory>
     */
    private static array $reservedMemoryPools = [];

    private static null|false|int $systemMemoryLimit = null;

    public readonly false|int $memoryLimit;

    public function __construct(
        private readonly bool $realUsage = true,
    ) {
        $this->memoryLimit = self::getSystemMemoryLimit();
    }

    public function getReservedMemoryPool(
        string $reference,
    ): null|ReservedMemory {
        return self::$reservedMemoryPools[$reference] ?? null;
    }

    /**
     * @return \Northrook\Runtime\ReservedMemory[]
     */
    public function getReservedMemoryPools(): array
    {
        return self::$reservedMemoryPools;
    }

    public function reserveMemory(
        int         $bytes,
        null|string $reference = null,
    ): void {
        $reference ??= \spl_object_id($this) . '#' . $bytes;

        ( self::$reservedMemoryPools[$reference] ??= new ReservedMemory($bytes) )->reserve();
    }

    public function releaseMemory(
        string $reference,
    ): void {
        self::$reservedMemoryPools[$reference]->release();
    }

    public function releaseAllMemory(): void
    {
        foreach (self::$reservedMemoryPools as $pool) {
            $pool->release();
        }
    }

    public function memoryUsage(
        null|bool $realUsage = null,
    ): int {
        return \memory_get_usage($realUsage ?? $this->realUsage);
    }

    public function memoryPeakUsage(
        null|bool $realUsage = null,
    ): int {
        return \memory_get_peak_usage($realUsage ?? $this->realUsage);
    }

    public function memoryRemaining(
        null|bool $realUsage = null,
    ): int|true {
        if (! $this->memoryLimit) {
            return true;
        }

        return \max(0, $this->memoryLimit - $this->memoryUsage($realUsage ?? $this->realUsage));
    }

    public static function getMemoryRemaining(
        bool $realUsage = true,
    ): int|true {
        return new self($realUsage)->memoryRemaining();
    }

    public static function reset(): void
    {
        self::$reservedMemoryPools = [];
        self::$systemMemoryLimit   = null;
    }

    public static function getSystemMemoryLimit(): false|int
    {
        if (self::$systemMemoryLimit === null) {
            $memoryLimit = \php_ini_bytes(\ini_get('memory_limit'));

            self::$systemMemoryLimit = $memoryLimit < 0
                ? false
                : $memoryLimit;
        }

        return self::$systemMemoryLimit;
    }
}

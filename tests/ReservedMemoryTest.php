<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\ReservedMemory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservedMemoryTest extends TestCase
{
    public function testConstructDoesNotReserve(): void
    {
        $memory = new ReservedMemory(64);

        self::assertSame(64, $memory->bytes());
        self::assertFalse($memory->isReserved());
    }

    public function testReserveReleaseLifecycle(): void
    {
        $memory = new ReservedMemory(64);

        $memory->reserve();
        self::assertTrue($memory->isReserved());
        self::assertSame(64, $memory->bytes());

        $memory->release();
        self::assertFalse($memory->isReserved());
        self::assertSame(64, $memory->bytes());
    }

    public function testReserveIsIdempotent(): void
    {
        $memory = new ReservedMemory(64);

        $memory->reserve();
        $memory->reserve();

        self::assertTrue($memory->isReserved());
    }

    public function testReleaseWhenEmptyIsNoOp(): void
    {
        $memory = new ReservedMemory(64);

        $memory->release();

        self::assertFalse($memory->isReserved());
    }

    #[DataProvider('provideInvalidBytes')]
    public function testRejectsOutOfRangeBytes(
        int $bytes,
    ): void {
        $this->expectException(RuntimeException::class);

        new ReservedMemory($bytes);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideInvalidBytes(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above max' => [16_777_217];
    }

    #[DataProvider('provideBoundaryBytes')]
    public function testAcceptsBoundaryBytes(
        int $bytes,
    ): void {
        $memory = new ReservedMemory($bytes);

        self::assertSame($bytes, $memory->bytes());
        self::assertFalse($memory->isReserved());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideBoundaryBytes(): iterable
    {
        yield 'min' => [1];
        yield 'max' => [16_777_216];
    }
}

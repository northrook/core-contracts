<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ReservedMemory;
use Northrook\Contracts\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReservedMemoryTest extends TestCase
{
    public function testConstructorDoesNotReserve(): void
    {
        $memory = new ReservedMemory(1_024);

        self::assertFalse($memory->isReserved());
        self::assertSame(1_024, $memory->bytes());
    }

    public function testReserveReleaseLifecycle(): void
    {
        $memory = new ReservedMemory(1_024);

        $memory->reserve();
        self::assertTrue($memory->isReserved());

        $memory->release();
        self::assertFalse($memory->isReserved());
    }

    public function testReserveIsIdempotent(): void
    {
        $memory = new ReservedMemory(1_024);

        $memory->reserve();
        $memory->reserve();

        self::assertTrue($memory->isReserved());
        self::assertSame(1_024, $memory->bytes());
    }

    public function testReleaseWithoutReserveIsNoOp(): void
    {
        $memory = new ReservedMemory(1_024);

        $memory->release();

        self::assertFalse($memory->isReserved());
    }

    public function testReserveAfterReleaseReallocates(): void
    {
        $memory = new ReservedMemory(1_024);

        $memory->reserve();
        $memory->release();
        $memory->reserve();

        self::assertTrue($memory->isReserved());
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function provideValidByteCounts(): \Generator
    {
        yield 'minimum' => [1];
        yield 'arbitrary' => [65_536];
        yield 'maximum' => [16_777_216];
    }

    #[DataProvider('provideValidByteCounts')]
    public function testConstructorAcceptsByteRange(
        int $bytes,
    ): void {
        $memory = new ReservedMemory($bytes);

        self::assertSame($bytes, $memory->bytes());
        self::assertFalse($memory->isReserved());
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function provideInvalidByteCounts(): \Generator
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above maximum' => [16_777_217];
    }

    #[DataProvider('provideInvalidByteCounts')]
    public function testConstructorRejectsOutOfRangeBytes(
        int $bytes,
    ): void {
        $this->expectException(RuntimeException::class);
        new ReservedMemory($bytes);
    }
}

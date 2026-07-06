<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\Timestamp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimestampTest extends TestCase
{
    #[DataProvider('provideValidTimestamps')]
    public function testAcceptsPreMillenniumValues(
        null|string|int|float $input,
        int $expectedNumber,
        string $expectedString,
    ): void {
        $timestamp = new Timestamp($input);

        self::assertSame($expectedNumber, $timestamp->number);
        self::assertSame($expectedString, $timestamp->string);
        self::assertSame($expectedString, (string) $timestamp);
    }

    /**
     * @return iterable<string, array{null|string|int|float, int, string}>
     */
    public static function provideValidTimestamps(): iterable
    {
        yield 'epoch int' => [0, 0, '0000000000000'];
        yield 'epoch float seconds' => [0.0, 0, '0000000000000'];
        yield 'epoch string ms' => ['0', 0, '0000000000000'];
        yield 'epoch decimal seconds' => ['0.0', 0, '0000000000000'];
        yield 'pre-13-digit era int' => [978_307_200_000, 978_307_200_000, '0978307200000'];
        yield 'pre-13-digit era decimal seconds' => [978_307_200.5, 978_307_200_500, '0978307200500'];
        yield 'millennium boundary int' => [1_000_000_000_000, 1_000_000_000_000, '1000000000000'];
        yield 'max valid instant' => [4_102_444_800_999, 4_102_444_800_999, '4102444800999'];
    }

    #[DataProvider('provideInvalidTimestamps')]
    public function testRejectsOutOfRangeValues(
        string|int|float $input,
    ): void {
        $this->expectException(RuntimeException::class);

        new Timestamp($input);
    }

    /**
     * @return iterable<string, array{string|int|float}>
     */
    public static function provideInvalidTimestamps(): iterable
    {
        yield 'negative int' => [-1];
        yield 'negative float seconds' => [-0.001];
        yield 'after year 2100' => [4_102_444_800_1000];
    }

    public function testToDateTimeFromEpoch(): void
    {
        $dateTime = new Timestamp(0)->toDateTime(new \DateTimeZone('UTC'));

        self::assertSame('1970-01-01 00:00:00.000', $dateTime->format('Y-m-d H:i:s.v'));
    }

}

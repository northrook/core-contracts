<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Context;
use Northrook\Contracts\Tests\Support\ResetsContext;
use Northrook\RuntimeException;
use Northrook\Timestamp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimestampTest extends TestCase
{
    protected function tearDown(): void
    {
        ResetsContext::reset();
    }

    // -------------------------------------------------------------------------
    // Construction — valid inputs
    // -------------------------------------------------------------------------

    #[DataProvider('provideValidTimestamps')]
    public function testConstructAcceptsValidRepresentations(
        null|string|int|float $input,
        int                   $expectedNumber,
    ): void {
        $timestamp = new Timestamp($input);

        self::assertSame($expectedNumber, $timestamp->number);
        self::assertSame(\sprintf('%013d', $expectedNumber), $timestamp->string);
        self::assertSame(13, \strlen($timestamp->string));
        self::assertSame($timestamp->string, (string) $timestamp);
        self::assertNull($timestamp->precision);
    }

    /**
     * @return \Generator<string, array{null|string|int|float, int}>
     */
    public static function provideValidTimestamps(): \Generator
    {
        yield 'epoch int' => [0, 0];
        yield 'epoch float' => [0.0, 0];
        yield 'epoch string' => ['0', 0];
        yield 'int milliseconds' => [1_700_000_000_000, 1_700_000_000_000];
        yield 'float seconds truncate' => [1_700_000_000.9999, 1_700_000_000_999];
        yield 'string milliseconds' => ['1700000000000', 1_700_000_000_000];
        yield 'decimal second string' => ['1700000000.5', 1_700_000_000_500];
        yield 'pre-13-digit era int' => [123_456_789, 123_456_789];
        yield 'pre-millennium string' => ['951782400000', 951_782_400_000];
        yield 'millennium boundary' => [1_000_000_000_000, 1_000_000_000_000];
        yield 'max valid instant' => [4_102_444_800_000, 4_102_444_800_000];
    }

    public function testStringIsZeroPaddedToThirteenDigits(): void
    {
        self::assertSame('0000000000000', new Timestamp(0)->string);
        self::assertSame('0000000000001', new Timestamp(1)->string);
        self::assertSame('0000123456789', new Timestamp(123_456_789)->string);
    }

    // -------------------------------------------------------------------------
    // Construction — invalid inputs
    // -------------------------------------------------------------------------

    #[DataProvider('provideInvalidTimestamps')]
    public function testRejectsOutOfRangeValues(
        string|int|float $input,
    ): void {
        $this->expectException(RuntimeException::class);

        new Timestamp($input);
    }

    /**
     * @return \Generator<string, array{string|int|float}>
     */
    public static function provideInvalidTimestamps(): \Generator
    {
        yield 'negative int' => [-1];
        yield 'negative float' => [-0.001];
        yield 'negative string' => ['-1'];
        yield 'post-2100' => [4_102_444_801_000];
        yield 'far future float seconds' => [9_999_999_999.0];
    }

    // -------------------------------------------------------------------------
    // now() / number()
    // -------------------------------------------------------------------------

    public function testNowCapturesPrecisionAndCurrentTime(): void
    {
        $before    = (int) \floor(\microtime(true) * 1000);
        $timestamp = Timestamp::now();
        $after     = (int) \floor(\microtime(true) * 1000);

        self::assertIsInt($timestamp->precision);
        self::assertGreaterThanOrEqual($before, $timestamp->number);
        self::assertLessThanOrEqual($after, $timestamp->number);
    }

    public function testNullConstructorBehavesLikeNow(): void
    {
        $timestamp = new Timestamp;

        self::assertIsInt($timestamp->precision);
        self::assertSame(13, \strlen($timestamp->string));
    }

    public function testNumberHelper(): void
    {
        self::assertSame(1_700_000_000_000, Timestamp::number(1_700_000_000_000));
        self::assertSame(1_700_000_000_500, Timestamp::number('1700000000.5'));
    }

    // -------------------------------------------------------------------------
    // toDateTime() / format()
    // -------------------------------------------------------------------------

    public function testToDateTimeFromEpoch(): void
    {
        $datetime = new Timestamp(0)->toDateTime(new \DateTimeZone('UTC'));

        self::assertSame('1970-01-01 00:00:00.000', $datetime->format('Y-m-d H:i:s.v'));
        self::assertSame('0', $datetime->format('U'));
    }

    public function testToDateTimeAppliesTimezone(): void
    {
        $datetime = new Timestamp(0)->toDateTime(new \DateTimeZone('Europe/Oslo'));

        self::assertSame('Europe/Oslo', $datetime->getTimezone()->getName());
        self::assertSame('1970-01-01 01:00:00.000', $datetime->format('Y-m-d H:i:s.v'));
    }

    public function testToDateTimeDefaultsToContextTimezone(): void
    {
        Context::register(
            rootDirectory: \dirname(__DIR__),
            timezone     : 'Europe/Oslo',
        );

        $datetime = new Timestamp(0)->toDateTime();

        self::assertSame('Europe/Oslo', $datetime->getTimezone()->getName());
    }

    public function testToDateTimeFallsBackToPhpDefaultTimezoneWhenUnregistered(): void
    {
        self::assertFalse(Context::isRegistered());

        $previous = \date_default_timezone_get();
        \date_default_timezone_set('Asia/Tokyo');

        try {
            $datetime = new Timestamp(0)->toDateTime();

            self::assertSame('Asia/Tokyo', $datetime->getTimezone()->getName());
            self::assertFalse(Context::isRegistered());
        }
        finally {
            \date_default_timezone_set($previous);
        }
    }

    public function testFormatDefaultsToIso8601WithMilliseconds(): void
    {
        Context::register(
            timezone     : 'UTC',
            rootDirectory: \dirname(__DIR__),
        );

        $formatted = new Timestamp(0)->format();

        self::assertSame('1970-01-01T00:00:00.000+00:00', $formatted);
    }

    public function testFormatAcceptsCustomPattern(): void
    {
        $formatted = new Timestamp(1_700_000_000_000)
            ->toDateTime(new \DateTimeZone('UTC'))
            ->format('Y');

        self::assertSame('2023', $formatted);
    }

    #[DataProvider('provideMillisecondRemainders')]
    public function testToDateTimePreservesMilliseconds(
        int $remainder,
    ): void {
        $milliseconds = 1_700_000_000_000 + $remainder;
        $datetime     = new Timestamp($milliseconds)->toDateTime(new \DateTimeZone('UTC'));

        self::assertSame($remainder, (int) $datetime->format('v'));
        self::assertSame(1_700_000_000, (int) $datetime->format('U'));
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function provideMillisecondRemainders(): \Generator
    {
        yield 'zero' => [0];
        yield 'single digit' => [5];
        yield 'two digits' => [50];
        yield 'two digits near boundary' => [99];
        yield 'three digits' => [100];
        yield 'arbitrary' => [234];
        yield 'max' => [999];
    }
}

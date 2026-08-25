<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\InvalidArgumentException;
use Northrook\Timezone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimezoneTest extends TestCase
{
    // -------------------------------------------------------------------------
    // from() — absence / defaults
    // -------------------------------------------------------------------------

    public function testFromNullDefaultsToUtc(): void
    {
        $timezone = Timezone::from(null);

        self::assertSame('UTC', $timezone->identifier);
        self::assertSame(0, $timezone->offset);
        self::assertSame(0, $timezone->getOffset());
    }

    public function testFromOmittedValueDefaultsToUtc(): void
    {
        $timezone = Timezone::from();

        self::assertSame('UTC', $timezone->identifier);
    }

    public function testFromEmptyStringFallsBackToDefault(): void
    {
        self::assertSame('UTC', Timezone::from('')->identifier);
        self::assertSame('UTC', Timezone::from('   ')->identifier);
    }

    public function testFromEmptyStringUsesCustomDefault(): void
    {
        $timezone = Timezone::from("\t", 'Europe/Oslo');

        self::assertSame('Europe/Oslo', $timezone->identifier);
    }

    public function testFromNullUsesCustomDefault(): void
    {
        $timezone = Timezone::from(null, 'Etc/UTC');

        self::assertSame('Etc/UTC', $timezone->identifier);
    }

    // -------------------------------------------------------------------------
    // from() — identity / native types
    // -------------------------------------------------------------------------

    public function testFromReturnsSameInstanceForTimezone(): void
    {
        $original = Timezone::from('UTC');

        self::assertSame($original, Timezone::from($original));
    }

    public function testFromDateTimeZoneUsesCanonicalName(): void
    {
        $native   = new \DateTimeZone('+0200');
        $timezone = Timezone::from($native);

        self::assertSame('+02:00', $timezone->identifier);
        self::assertInstanceOf(Timezone::class, $timezone);
        self::assertNotSame($native, $timezone);
    }

    public function testFromDateTimeImmutableUsesZone(): void
    {
        $datetime = new \DateTimeImmutable(
            '2026-01-15T12:00:00',
            new \DateTimeZone('Asia/Tokyo'),
        );

        self::assertSame('Asia/Tokyo', Timezone::from($datetime)->identifier);
    }

    public function testFromDateTimeUsesZone(): void
    {
        $datetime = new \DateTime(
            '2026-06-01 08:00:00',
            new \DateTimeZone('America/New_York'),
        );

        self::assertSame('America/New_York', Timezone::from($datetime)->identifier);
    }

    public function testFromStringableTrimsIdentifier(): void
    {
        $timezone = Timezone::from(new readonly class implements \Stringable {
            public function __toString(): string
            {
                return '  Pacific/Auckland  ';
            }
        });

        self::assertSame('Pacific/Auckland', $timezone->identifier);
    }

    public function testFromStringableBlankFallsBackToDefault(): void
    {
        $timezone = Timezone::from(new readonly class implements \Stringable {
            public function __toString(): string
            {
                return '   ';
            }
        }, 'UTC');

        self::assertSame('UTC', $timezone->identifier);
    }

    // -------------------------------------------------------------------------
    // from() — minute offsets (DateTime.ts TimezoneOffset parity)
    // -------------------------------------------------------------------------

    #[DataProvider('provideMinuteOffsets')]
    public function testFromIntMinutesBuildsFixedOffset(
        int    $minutes,
        string $identifier,
        int    $seconds,
    ): void {
        $timezone = Timezone::from($minutes);

        self::assertSame($identifier, $timezone->identifier);
        self::assertSame($minutes, $timezone->offset);
        self::assertSame($seconds, $timezone->getOffset());
        self::assertSame($seconds, $timezone->getOffset(
            new \DateTimeImmutable('2026-01-01T00:00:00', new \DateTimeZone('UTC')),
        ));
        self::assertSame($seconds, $timezone->getOffset(
            new \DateTimeImmutable('2026-07-01T00:00:00', new \DateTimeZone('UTC')),
        ));
    }

    /**
     * @return iterable<string, array{int, non-empty-string, int}>
     */
    public static function provideMinuteOffsets(): iterable
    {
        yield 'zero' => [0, '+00:00', 0];
        yield 'positive hours' => [120, '+02:00', 7_200];
        yield 'negative hours' => [-300, '-05:00', -18_000];
        yield 'half hour' => [330, '+05:30', 19_800];
        yield 'negative half hour' => [-570, '-09:30', -34_200];
        yield 'single-digit hour pads' => [60, '+01:00', 3_600];
        yield 'single-digit minute pads' => [61, '+01:01', 3_660];
    }

    // -------------------------------------------------------------------------
    // from() — string identifiers / offset forms PHP accepts
    // -------------------------------------------------------------------------

    #[DataProvider('provideStringIdentifiers')]
    public function testFromStringIdentifier(
        string $input,
        string $identifier,
    ): void {
        self::assertSame($identifier, Timezone::from($input)->identifier);
    }

    /**
     * @return iterable<string, array{non-empty-string, non-empty-string}>
     */
    public static function provideStringIdentifiers(): iterable
    {
        yield 'iana' => ['Europe/Oslo', 'Europe/Oslo'];
        yield 'utc' => ['UTC', 'UTC'];
        yield 'etc utc' => ['Etc/UTC', 'Etc/UTC'];
        yield 'trimmed iana' => ['  Europe/Berlin  ', 'Europe/Berlin'];
        yield 'offset colon' => ['+02:00', '+02:00'];
        yield 'offset compact' => ['-0530', '-05:30'];
        yield 'offset short' => ['+2', '+02:00'];
        yield 'gmt prefix' => ['GMT+2', '+02:00'];
        yield 'offset with seconds stripped by name' => ['+01:00:00', '+01:00'];
    }

    #[DataProvider('provideOffsetStringsWithMinutes')]
    public function testFromOffsetStringMinutes(
        string $input,
        int    $minutes,
    ): void {
        self::assertSame($minutes, Timezone::from($input)->offset);
    }

    /**
     * @return iterable<string, array{non-empty-string, int}>
     */
    public static function provideOffsetStringsWithMinutes(): iterable
    {
        yield 'plus two' => ['+02:00', 120];
        yield 'minus five thirty' => ['-05:30', -330];
        yield 'gmt plus two' => ['GMT+2', 120];
    }

    // -------------------------------------------------------------------------
    // from() — failures
    // -------------------------------------------------------------------------

    public function testFromInvalidIdentifierThrowsWithContext(): void
    {
        try {
            Timezone::from('Not/AZone');
            self::fail('Expected InvalidArgumentException');
        }
        catch (InvalidArgumentException $exception) {
            self::assertSame('Not/AZone', $exception->getContext()['from']);
            self::assertSame('Not/AZone', $exception->getContext()['resolved']);
            self::assertSame('UTC', $exception->getContext()['default']);
            self::assertNotNull($exception->getPrevious());
        }
    }

    public function testFromInvalidIntPathPreservesOriginalInContext(): void
    {
        // Extreme minute values can produce zone strings PHP rejects.
        try {
            Timezone::from(100_000);
            self::fail('Expected InvalidArgumentException');
        }
        catch (InvalidArgumentException $exception) {
            self::assertSame(100_000, $exception->getContext()['from']);
            self::assertIsString($exception->getContext()['resolved']);
            self::assertNotNull($exception->getPrevious());
        }
    }

    public function testFromInvalidDefaultThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Timezone::from(null, 'Still/NotAZone');
    }

    public function testFromInvalidDefaultWhenValueEmptyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Timezone::from('', 'Still/NotAZone');
    }

    // -------------------------------------------------------------------------
    // Properties & string cast
    // -------------------------------------------------------------------------

    public function testIdentifierMatchesGetName(): void
    {
        $timezone = Timezone::from('America/New_York');

        self::assertSame($timezone->getName(), $timezone->identifier);
    }

    public function testOffsetMinutesAreTruncatedFromSeconds(): void
    {
        $timezone = Timezone::from(195);

        self::assertSame(11_700, $timezone->getOffset());
        self::assertSame(195, $timezone->offset);
        self::assertSame(
            \intdiv($timezone->getOffset(), 60),
            $timezone->offset,
        );
    }

    public function testToStringReturnsIdentifier(): void
    {
        $timezone = Timezone::from('Europe/London');

        self::assertSame('Europe/London', (string) $timezone);
        self::assertSame($timezone->identifier, $timezone->__toString());
    }

    public function testImplementsStringable(): void
    {
        self::assertInstanceOf(\Stringable::class, Timezone::from('UTC'));
    }

    public function testExtendsDateTimeZone(): void
    {
        self::assertInstanceOf(\DateTimeZone::class, Timezone::from('UTC'));
    }

    // -------------------------------------------------------------------------
    // getOffset()
    // -------------------------------------------------------------------------

    public function testGetOffsetDefaultsToNow(): void
    {
        $timezone = Timezone::from('+01:00');

        self::assertSame(3_600, $timezone->getOffset());
        self::assertSame(3_600, $timezone->getOffset(null));
    }

    public function testGetOffsetAtInstantIsDstAware(): void
    {
        $timezone = Timezone::from('Europe/Oslo');
        $winter   = new \DateTimeImmutable('2026-01-15T12:00:00', new \DateTimeZone('UTC'));
        $summer   = new \DateTimeImmutable('2026-07-15T12:00:00', new \DateTimeZone('UTC'));

        self::assertSame(3_600, $timezone->getOffset($winter));
        self::assertSame(7_200, $timezone->getOffset($summer));
    }

    public function testGetOffsetAcceptsMutableDateTime(): void
    {
        $timezone = Timezone::from('UTC');
        $datetime = new \DateTime('2026-03-01 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame(0, $timezone->getOffset($datetime));
    }

    public function testCurrentOffsetForNamedZoneMatchesGetOffsetNow(): void
    {
        $timezone = Timezone::from('Europe/Oslo');

        self::assertSame(
            \intdiv($timezone->getOffset(), 60),
            $timezone->offset,
        );
    }

    // -------------------------------------------------------------------------
    // Round-trips / interoperability
    // -------------------------------------------------------------------------

    public function testMinuteOffsetRoundTripsThroughFrom(): void
    {
        $original = Timezone::from(120);
        $again    = Timezone::from($original->offset);

        self::assertSame($original->identifier, $again->identifier);
        self::assertSame($original->offset, $again->offset);
    }

    public function testIdentifierRoundTripsThroughFrom(): void
    {
        $original = Timezone::from('Europe/Paris');
        $again    = Timezone::from((string) $original);

        self::assertSame($original->identifier, $again->identifier);
        self::assertNotSame($original, $again);
    }

    public function testUsableWithDateTimeImmutableSetTimezone(): void
    {
        $timezone = Timezone::from('Asia/Tokyo');
        $utc      = new \DateTimeImmutable('2026-01-01T00:00:00', new \DateTimeZone('UTC'));
        $local    = $utc->setTimezone($timezone);

        self::assertSame('Asia/Tokyo', $local->getTimezone()->getName());
        self::assertSame('2026-01-01T09:00:00+09:00', $local->format('c'));
    }

    public function testConstructorStillAcceptsDirectIdentifier(): void
    {
        $timezone = new Timezone('Australia/Sydney');

        self::assertSame('Australia/Sydney', $timezone->identifier);
        self::assertSame((string) $timezone, $timezone->getName());
    }
}

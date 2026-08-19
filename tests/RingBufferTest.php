<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\InvalidArgumentException;
use Northrook\RingBuffer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RingBufferTest extends TestCase
{
    public function testEmptyBufferDefaults(): void
    {
        $buffer = new RingBuffer(3);

        self::assertSame(3, $buffer->capacity);
        self::assertSame(0, $buffer->size);
        self::assertSame(0, $buffer->count());
        self::assertSame(0, $buffer->offset);
        self::assertFalse($buffer->isFull);
        self::assertNull($buffer->newest);
        self::assertNull($buffer->oldest);
        self::assertSame([], $buffer->values());
        self::assertSame([], $buffer->values(reverse: true));
        self::assertSame([], \iterator_to_array($buffer, false));
        self::assertSame([], \iterator_to_array($buffer->entries(), true));
        self::assertTrue($buffer->every(static fn(): bool => false));
    }

    #[DataProvider('provideInvalidCapacities')]
    public function testConstructorRejectsNonPositiveCapacity(
        int $capacity,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RingBuffer capacity must be a positive integer greater than 0.');

        new RingBuffer($capacity);
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function provideInvalidCapacities(): \Generator
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'large negative' => [\PHP_INT_MIN];
    }

    public function testPushFillsInMostRecentFirstOrder(): void
    {
        $buffer = new RingBuffer(3);

        self::assertSame(
            ['entry' => 'a', 'overwritten' => false],
            $buffer->push('a'),
        );
        self::assertSame(
            ['entry' => 'b', 'overwritten' => false],
            $buffer->push('b'),
        );
        self::assertSame(
            ['entry' => 'c', 'overwritten' => false],
            $buffer->push('c'),
        );

        self::assertSame(3, $buffer->size);
        self::assertTrue($buffer->isFull);
        self::assertSame('c', $buffer->newest);
        self::assertSame('a', $buffer->oldest);
        self::assertSame(['c', 'b', 'a'], $buffer->values());
        self::assertSame(['a', 'b', 'c'], $buffer->values(reverse: true));
        self::assertSame('c', $buffer->at(0));
        self::assertSame('b', $buffer->at(1));
        self::assertSame('a', $buffer->at(2));
    }

    public function testPushOverwritesOldestWhenFull(): void
    {
        $buffer = new RingBuffer(3);
        $buffer->push('a');
        $buffer->push('b');
        $buffer->push('c');

        $result = $buffer->push('d');

        self::assertTrue($result['overwritten']);
        self::assertSame('d', $result['entry']);
        self::assertSame(3, $buffer->size);
        self::assertTrue($buffer->isFull);
        self::assertSame(['d', 'c', 'b'], $buffer->values());
        self::assertSame('d', $buffer->newest);
        self::assertSame('b', $buffer->oldest);
        self::assertFalse($buffer->has('a'));
        self::assertTrue($buffer->has('b'));
    }

    public function testMultipleWrapAroundsPreserveCapacityAndOrder(): void
    {
        $buffer = new RingBuffer(2);

        foreach (['a', 'b', 'c', 'd', 'e'] as $entry) {
            $buffer->push($entry);
        }

        self::assertSame(2, $buffer->size);
        self::assertSame(['e', 'd'], $buffer->values());
        self::assertSame('e', $buffer->newest);
        self::assertSame('d', $buffer->oldest);
        self::assertSame(1, $buffer->offset);
    }

    public function testCapacityOneAlwaysOverwritesAfterFirst(): void
    {
        $buffer = new RingBuffer(1);

        self::assertFalse($buffer->push('first')['overwritten']);
        self::assertTrue($buffer->isFull);
        self::assertSame(['first'], $buffer->values());
        self::assertSame('first', $buffer->newest);
        self::assertSame('first', $buffer->oldest);

        self::assertTrue($buffer->push('second')['overwritten']);
        self::assertSame(['second'], $buffer->values());
        self::assertSame('second', $buffer->newest);
        self::assertSame('second', $buffer->oldest);
        self::assertSame(0, $buffer->offset);
    }

    public function testAtReturnsNullForInvalidOffsets(): void
    {
        $buffer = new RingBuffer(2);
        $buffer->push('a');

        self::assertNull($buffer->at(-1));
        self::assertNull($buffer->at(1));
        self::assertNull($buffer->at(99));
        self::assertSame('a', $buffer->at(0));
    }

    public function testIteratorAndEntriesAreMostRecentFirst(): void
    {
        $buffer = new RingBuffer(3);
        $buffer->push('a');
        $buffer->push('b');
        $buffer->push('c');

        self::assertSame(['c', 'b', 'a'], \iterator_to_array($buffer, false));
        self::assertSame(
            [0 => 'c', 1 => 'b', 2 => 'a'],
            \iterator_to_array($buffer->entries(), true),
        );
    }

    public function testHasStrictVersusLooseEquality(): void
    {
        $buffer = new RingBuffer(3);
        $buffer->push(0);
        $buffer->push('');
        $buffer->push(null);

        self::assertTrue($buffer->has(0));
        self::assertTrue($buffer->has(''));
        self::assertTrue($buffer->has(null));

        self::assertFalse($buffer->has(false));
        self::assertFalse($buffer->has('0'));

        self::assertTrue($buffer->has(false, strict: false));
        self::assertTrue($buffer->has('0', strict: false));
        self::assertTrue($buffer->has(0, strict: false));
    }

    public function testHasUsesObjectIdentityUnderStrictComparison(): void
    {
        $a      = new \stdClass;
        $b      = new \stdClass;
        $buffer = new RingBuffer(2);
        $buffer->push($a);

        self::assertTrue($buffer->has($a));
        self::assertFalse($buffer->has($b));
        self::assertFalse($buffer->has(clone $a));
    }

    public function testClearResetsStateAndAllowsReuse(): void
    {
        $buffer = new RingBuffer(3);
        $buffer->push('a');
        $buffer->push('b');
        $buffer->push('c');
        $buffer->push('d');

        $buffer->clear();

        self::assertSame(0, $buffer->size);
        self::assertSame(0, $buffer->count());
        self::assertSame(0, $buffer->offset);
        self::assertFalse($buffer->isFull);
        self::assertNull($buffer->newest);
        self::assertNull($buffer->oldest);
        self::assertSame([], $buffer->values());
        self::assertFalse($buffer->has('d'));

        $buffer->push('z');
        self::assertSame(['z'], $buffer->values());
        self::assertFalse($buffer->push('y')['overwritten']);
        self::assertSame(['y', 'z'], $buffer->values());
    }

    public function testEveryPassesEntryAndMostRecentFirstIndex(): void
    {
        $buffer = new RingBuffer(3);
        $buffer->push('a');
        $buffer->push('b');
        $buffer->push('c');

        $seen = [];
        self::assertTrue(
            $buffer->every(static function(
                mixed $entry,
                int   $index,
            ) use (&$seen): bool {
                $seen[] = [$index, $entry];
                return true;
            }),
        );
        self::assertSame([[0, 'c'], [1, 'b'], [2, 'a']], $seen);

        self::assertFalse(
            $buffer->every(static fn(mixed $entry): bool => $entry !== 'b'),
        );
    }

    public function testStoredNullIsDistinguishableFromEmptyOnlyViaSize(): void
    {
        $buffer = new RingBuffer(2);
        $buffer->push(null);

        self::assertSame(1, $buffer->size);
        self::assertNull($buffer->newest);
        self::assertNull($buffer->oldest);
        self::assertNull($buffer->at(0));
        self::assertNull($buffer->at(1));
        self::assertTrue($buffer->has(null));
        self::assertSame([null], $buffer->values());
    }

    public function testCountMatchesSizeAcrossLifecycle(): void
    {
        $buffer = new RingBuffer(2);

        self::assertCount(0, $buffer);
        $buffer->push(1);
        self::assertCount(1, $buffer);
        $buffer->push(2);
        self::assertCount(2, $buffer);
        $buffer->push(3);
        self::assertCount(2, $buffer);
        self::assertSame($buffer->size, $buffer->count());
    }

    public function testOffsetTracksWriteCursorThroughWrap(): void
    {
        $buffer = new RingBuffer(3);

        self::assertSame(0, $buffer->offset);
        $buffer->push('a');
        self::assertSame(1, $buffer->offset);
        $buffer->push('b');
        self::assertSame(2, $buffer->offset);
        $buffer->push('c');
        self::assertSame(0, $buffer->offset);
        $buffer->push('d');
        self::assertSame(1, $buffer->offset);
    }

    /**
     * @noinspection PhpReadonlyPropertyWrittenOutsideDeclarationScopeInspection
     * @noinspection PhpObjectFieldsAreOnlyWrittenInspection
     */
    public function testCapacityIsReadonly(): void
    {
        $buffer = new RingBuffer(4);

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line intentional mutation attempt */
        $buffer->capacity = 8;
    }
}

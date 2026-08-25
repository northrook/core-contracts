<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Set;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SetTest extends TestCase
{
    public function testEmptyDefaults(): void
    {
        $set = self::set();

        self::assertSame(0, $set->size());
        self::assertSame(0, $set->count());
        self::assertTrue($set->isEmpty());
        self::assertSame([], $set->values());
        self::assertSame([], \iterator_to_array($set));
        self::assertSame('[]', \json_encode($set, \JSON_THROW_ON_ERROR));
    }

    public function testAddSkipsDuplicatesWithoutReordering(): void
    {
        $set = self::set(['a', 'b']);
        $set->add('a', 'c', 'b');

        self::assertSame(['a', 'b', 'c'], $set->values());
        self::assertSame(3, $set->size());
    }

    public function testAppendMovesExistingValuesToEnd(): void
    {
        $set = self::set(['a', 'b', 'c']);
        $set->append('a', 'd');

        self::assertSame(['b', 'c', 'a', 'd'], $set->values());
    }

    public function testPrependPreservesArgumentOrderAtFront(): void
    {
        $set = self::set(['c', 'd']);
        $set->prepend('a', 'b');

        self::assertSame(['a', 'b', 'c', 'd'], $set->values());

        $set->prepend('c');
        self::assertSame(['c', 'a', 'b', 'd'], $set->values());
    }

    public function testMergeUsesAddByDefaultAndAppendWhenOverriding(): void
    {
        $set = self::set(['a', 'b']);

        $set->merge(['b', 'c']);
        self::assertSame(['a', 'b', 'c'], $set->values());

        $set->merge(['a', 'd'], override: true);
        self::assertSame(['b', 'c', 'a', 'd'], $set->values());
    }

    public function testSortReordersWithoutChangingMembership(): void
    {
        $set = self::set(['c', 'a', 'b']);
        $set->sort(static fn(string $left, string $right): int => $left <=> $right);

        self::assertSame(['a', 'b', 'c'], $set->values());
        self::assertTrue($set->contains('a', 'b', 'c'));
    }

    public function testRemoveAndDelete(): void
    {
        $set = self::set(['a', 'b', 'c']);

        self::assertTrue($set->delete('b'));
        self::assertFalse($set->delete('missing'));
        self::assertSame(['a', 'c'], $set->values());

        $set->remove('a', 'missing', 'c');
        self::assertTrue($set->isEmpty());
    }

    public function testHasAndContainsUseStrictEquality(): void
    {
        $set = self::set([0, '', null, false]);

        self::assertTrue($set->has(0));
        self::assertTrue($set->has(''));
        self::assertTrue($set->has(null));
        self::assertTrue($set->has(false));
        self::assertFalse($set->has('0'));
        self::assertTrue($set->contains(0, '', null, false));
        self::assertFalse($set->contains(0, '0'));
    }

    public function testHasNanAndObjectIdentity(): void
    {
        $object = new \stdClass;
        $set    = self::set([\NAN, $object]);

        self::assertTrue($set->has(\NAN));
        self::assertTrue($set->has($object));
        self::assertFalse($set->has(clone $object));
    }

    public function testArrayValuesUseLinearLookup(): void
    {
        $entry = ['id' => 1];
        $set   = self::set([$entry, 'other']);

        self::assertTrue($set->has($entry));
        self::assertTrue($set->has(['id' => 1]));
        self::assertFalse($set->has(['id' => 2]));
        self::assertTrue($set->delete($entry));
        self::assertSame(['other'], $set->values());
    }

    public function testFloatValuesUseLinearLookup(): void
    {
        $set = self::set([1.5, 2.5]);

        self::assertTrue($set->has(1.5));
        self::assertTrue($set->delete(1.5));
        self::assertSame([2.5], $set->values());
    }

    public function testMapKeepsFirstMappedOccurrenceOnDuplicates(): void
    {
        $set    = self::set(['a', 'bb', 'c', 'dd']);
        $mapped = $set->map(static fn(string $value): int => \strlen($value));

        self::assertSame([1, 2], $mapped->values());
        self::assertNotSame($set, $mapped);
    }

    public function testFilterDefaultRemovesNullAndEmptyStringOnly(): void
    {
        $set      = self::set([null, '', false, 0, 0.0, 'kept']);
        $filtered = $set->filter();

        self::assertSame([false, 0, 0.0, 'kept'], $filtered->values());
    }

    public function testFilterWithCustomCallback(): void
    {
        $set      = self::set([1, 2, 3, 4]);
        $filtered = $set->filter(static fn(int $value): bool => $value % 2 === 0);

        self::assertSame([2, 4], $filtered->values());
    }

    public function testFirstAndLastThrowWhenEmpty(): void
    {
        $set = self::set();

        $this->expectException(\UnderflowException::class);
        $this->expectExceptionMessage('Set is empty.');

        $set->first();
    }

    public function testLastThrowsWhenEmpty(): void
    {
        $set = self::set();

        $this->expectException(\UnderflowException::class);
        $this->expectExceptionMessage('Set is empty.');

        $set->last();
    }

    public function testFirstAndLastReturnSequenceEnds(): void
    {
        $set = self::set(['first', 'middle', 'last']);

        self::assertSame('first', $set->first());
        self::assertSame('last', $set->last());
    }

    public function testArrayAccessUsesNumericStringOffsets(): void
    {
        $set = self::set(['a', 'b', 'c']);

        self::assertTrue(isset($set[0]));
        self::assertTrue(isset($set['1']));
        self::assertSame('a', $set[0]);
        self::assertSame('b', $set['1']);

        unset($set['0']);
        self::assertSame(['b', 'c'], $set->values());

        $set[] = 'd';
        self::assertSame(['b', 'c', 'd'], $set->values());
    }

    public function testOffsetGetThrowsForMissingIndex(): void
    {
        $set = self::set(['only']);

        $this->expectException(\OutOfRangeException::class);
        $this->expectExceptionMessage('Offset 1 does not exist.');

        $set[1];
    }

    public function testOffsetSetWithNonNullOffsetThrows(): void
    {
        $set = self::set(['a']);

        $this->expectException(\OutOfRangeException::class);
        $this->expectExceptionMessage('Set entries cannot be assigned by offset.');

        $set[0] = 'replacement';
    }

    #[DataProvider('provideInvalidOffsets')]
    public function testNormalizeOffsetRejectsInvalidOffsets(
        mixed  $offset,
        string $exceptionClass,
        string $message,
    ): void {
        $set = self::set(['a']);

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($message);

        $set->offsetExists($offset);
    }

    /**
     * @return \Generator<string, array{mixed, class-string<\Throwable>, string}>
     */
    public static function provideInvalidOffsets(): \Generator
    {
        yield 'negative integer' => [
            -1,
            \OutOfRangeException::class,
            'Offset must not be negative; `int` provided.',
        ];
        yield 'empty string' => [
            '',
            \OutOfRangeException::class,
            'Invalid offset; `string` provided.',
        ];
        yield 'non-numeric string' => [
            'abc',
            \OutOfRangeException::class,
            'Invalid offset; `string` provided.',
        ];
        yield 'float offset' => [
            1.5,
            \InvalidArgumentException::class,
            'Offset must be an integer or numeric string; `float` provided.',
        ];
    }

    public function testClearResetsMembershipAndOrder(): void
    {
        $set = self::set(['a', 'b']);
        $set->clear();

        self::assertTrue($set->isEmpty());
        self::assertSame([], $set->values());
        self::assertFalse($set->has('a'));

        $set->add('z');
        self::assertSame(['z'], $set->values());
    }

    public function testCopyAndSerializationAreIndependent(): void
    {
        $set  = self::set(['a', 'b']);
        $copy = $set->copy();
        $copy->append('c');
        $copy->delete('a');

        self::assertSame(['a', 'b'], $set->values());

        $serialized = \serialize($set);
        /** @var Set<string> $restored */
        $restored = \unserialize($serialized);
        $restored->append('d');

        self::assertSame(['a', 'b'], $set->values());
        self::assertSame(['a', 'b', 'd'], $restored->values());
        self::assertTrue($restored->has('a'));
    }

    public function testCountMatchesSizeAcrossLifecycle(): void
    {
        $set = self::set();

        self::assertCount(0, $set);
        $set->add('a');
        self::assertCount(1, $set);
        self::assertSame($set->size(), $set->count());
        $set->delete('a');
        self::assertCount(0, $set);
    }

    /**
     * @param list<mixed> $values
     *
     * @return Set<mixed>
     */
    private static function set(
        array $values = [],
    ): Set {
        return new Set($values);
    }
}

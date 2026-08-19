<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Record;
use PHPUnit\Framework\TestCase;

final class RecordTest extends TestCase
{
    public function testBasicAccessAndNativeKeySemantics(): void
    {
        $record = self::record([
            'name' => 'Northrook',
            0      => 'zero',
        ]);

        $record->set('enabled', true);
        $record->set('0', 'updated');

        self::assertSame('Northrook', $record->get('name'));
        self::assertSame('updated', $record->get(0));
        self::assertTrue($record->get('enabled'));
        self::assertNull($record->get('missing'));
        self::assertSame('fallback', $record->get('missing', 'fallback'));
        self::assertSame(3, $record->size);
        self::assertFalse($record->isEmpty);
        self::assertSame('Northrook', $record->first);
        self::assertTrue($record->last);
    }

    public function testStoredNullIsPresent(): void
    {
        $record = self::record(['nullable' => null]);

        self::assertTrue($record->has('nullable'));
        self::assertTrue($record->has('nullable', null));
        self::assertFalse($record->has('nullable', false));
        self::assertTrue(isset($record['nullable']));
        self::assertNull($record['nullable']);
    }

    public function testResolveOnlyCreatesAbsentValues(): void
    {
        $calls  = 0;
        $record = self::record(['existing' => null]);

        self::assertNull($record->resolve('existing', static fn(): string => 'unused'));
        self::assertSame(
            'created',
            $record->resolve('missing', static function() use (&$calls): string {
                ++$calls;

                return 'created';
            }),
        );
        self::assertSame('created', $record->resolve('missing', static fn(): string => 'unused'));
        self::assertSame(1, $calls);
    }

    public function testMergeAssignDeleteAndClear(): void
    {
        $record = self::record(['first' => 1, 'second' => 2]);

        $record->merge(['first' => 10, 'third' => 3], override: false);
        self::assertSame(['first' => 1, 'second' => 2, 'third' => 3], $record->all());

        $record->merge(['first' => 10]);
        self::assertSame(['first', 'second', 'third'], $record->keys());
        self::assertSame([10, 2, 3], $record->values());
        self::assertTrue($record->delete('second'));
        self::assertFalse($record->delete('missing'));

        $record->assign(['replacement' => 4]);
        self::assertSame(['replacement' => 4], $record->all());

        $record->clear();
        self::assertSame(0, $record->count());
        self::assertTrue($record->isEmpty);
        self::assertNull($record->first);
        self::assertNull($record->last);
    }

    public function testArrayAccessIterationJsonAndCopy(): void
    {
        $record      = self::record(['a' => 1]);
        $record['b'] = 2;
        $copy        = $record->copy();
        $copy['a']   = 10;
        unset($record['a']);

        self::assertSame(['b' => 2], \iterator_to_array($record));
        self::assertSame('{"b":2}', \json_encode($record, \JSON_THROW_ON_ERROR));
        self::assertSame(['a' => 10, 'b' => 2], $copy->all());
        self::assertFalse(isset($record['a']));
    }

    public function testCopyPreservesSparseIntegerKeys(): void
    {
        $record = self::record([0 => 'a', 2 => 'b', 'x' => 'c']);
        $copy   = $record->copy();
        $copy->set(0, 'changed');

        self::assertSame([0 => 'a', 2 => 'b', 'x' => 'c'], $record->all());
        self::assertSame([0 => 'changed', 2 => 'b', 'x' => 'c'], $copy->all());
        self::assertTrue($copy->has(2));
        self::assertFalse($copy->has(1));
    }

    public function testFirstAndLastWithStoredNull(): void
    {
        $record = self::record(['only' => null]);

        self::assertFalse($record->isEmpty);
        self::assertNull($record->first);
        self::assertNull($record->last);

        $record->set('trailing', 'present');
        self::assertNull($record->first);
        self::assertSame('present', $record->last);

        $record->assign(['leading' => 'present', 'trailing' => null]);
        self::assertSame('present', $record->first);
        self::assertNull($record->last);
    }

    public function testHasRejectsMismatchedValues(): void
    {
        $record = self::record(['status' => 'active']);

        self::assertTrue($record->has('status'));
        self::assertTrue($record->has('status', 'active'));
        self::assertFalse($record->has('status', 'inactive'));
        self::assertFalse($record->has('missing', 'active'));
    }

    public function testOffsetGetReturnsNullForMissingKey(): void
    {
        $record = self::record(['present' => 'value']);

        self::assertNull($record['missing']);
        self::assertFalse(isset($record['missing']));
        self::assertSame('value', $record['present']);
    }

    public function testMergeOverrideTrueUpdatesExistingValues(): void
    {
        $record = self::record(['keep' => 1, 'replace' => 2]);

        $record->merge(['replace' => 20, 'add' => 3]);

        self::assertSame(['keep' => 1, 'replace' => 20, 'add' => 3], $record->all());
    }

    public function testConstructorAcceptsIterable(): void
    {
        $entries = static function(): \Generator {
            yield 'from-generator' => 'value';
            yield 0 => 'zero';
        };

        $record = new Record($entries());

        self::assertSame('value', $record->get('from-generator'));
        self::assertSame('zero', $record->get(0));
        self::assertSame(['from-generator', 0], $record->keys());
    }

    public function testCountMatchesSizeAcrossLifecycle(): void
    {
        $record = self::record();

        self::assertCount(0, $record);
        self::assertSame(0, $record->size);
        $record->set('a', 1);
        self::assertCount(1, $record);
        self::assertSame($record->size, $record->count());
        $record->delete('a');
        self::assertCount(0, $record);
        self::assertTrue($record->isEmpty);
    }

    public function testUpdatePreservesInsertionOrder(): void
    {
        $record = self::record(['first' => 1, 'second' => 2, 'third' => 3]);
        $record->set('second', 20);

        self::assertSame(['first', 'second', 'third'], $record->keys());
        self::assertSame([1, 20, 3], $record->values());
    }

    /**
     * @param array<array-key, mixed> $entries
     *
     * @return Record<array-key, mixed>
     */
    private static function record(
        array $entries = [],
    ): Record {
        return new Record($entries);
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\InvalidArgumentException;
use Northrook\Map;
use PHPUnit\Framework\TestCase;

final class MapTest extends TestCase
{
    public function testEmptyDefaults(): void
    {
        $map = self::map();

        self::assertSame(0, $map->size);
        self::assertSame(0, $map->count());
        self::assertTrue($map->isEmpty);
        self::assertNull($map->first);
        self::assertNull($map->last);
        self::assertSame([], $map->keys());
        self::assertSame([], $map->values());
        self::assertSame([], $map->all());
        self::assertSame([], \iterator_to_array($map));
        self::assertFalse($map->any(static fn(): bool => true));
        self::assertTrue($map->every(static fn(): bool => false));
    }

    public function testStrictKeySemanticsKeepZeroAndStringZeroDistinct(): void
    {
        $map = self::map();
        $map->set(0, 'int-zero');
        $map->set('0', 'string-zero');

        self::assertSame(['int-zero', 'string-zero'], $map->values());
        self::assertSame([0, '0'], $map->keys());
        self::assertSame('int-zero', $map->get(0));
        self::assertSame('string-zero', $map->get('0'));
        self::assertTrue($map->has(0));
        self::assertTrue($map->has('0'));
    }

    public function testUpdatePreservesInsertionPosition(): void
    {
        $map = self::map([
            'first'  => 1,
            'second' => 2,
            'third'  => 3,
        ]);

        $map->set('second', 20);

        self::assertSame(['first', 'second', 'third'], $map->keys());
        self::assertSame([1, 20, 3], $map->values());
        self::assertSame(1, $map->first);
        self::assertSame(3, $map->last);
    }

    public function testGetHasAndDelete(): void
    {
        $map = self::map(['name' => 'Northrook']);

        self::assertSame('Northrook', $map->get('name'));
        self::assertNull($map->get('missing'));
        self::assertSame('fallback', $map->get('missing', 'fallback'));
        self::assertTrue($map->has('name'));
        self::assertTrue($map->has('name', 'Northrook'));
        self::assertFalse($map->has('name', 'other'));
        self::assertFalse($map->has('missing'));
        self::assertTrue($map->delete('name'));
        self::assertFalse($map->delete('name'));
        self::assertTrue($map->isEmpty);
    }

    /** @noinspection PhpDeprecatedSincePhp85Inspection */
    public function testStoredNullKeyAndValue(): void
    {
        $map = self::map();
        $map->set(null, null);

        self::assertTrue($map->has(null));
        self::assertTrue($map->has(null, null));
        self::assertFalse($map->has(null, false));
        self::assertNull($map->get(null));
        self::assertNull($map->first);
        self::assertNull($map->last);
        self::assertTrue(isset($map[null]));
        self::assertNull($map[null]);
    }

    public function testResolveOnlyCreatesAbsentValues(): void
    {
        $calls = 0;
        $map   = self::map();
        $map->set('existing', null);

        self::assertNull($map->resolve('existing', static fn(): string => 'unused'));
        self::assertSame(
            'created',
            $map->resolve('missing', static function() use (&$calls): string {
                ++$calls;

                return 'created';
            }),
        );
        self::assertSame('created', $map->resolve('missing', static fn(): string => 'unused'));
        self::assertSame(1, $calls);
    }

    public function testBooleanKeysAreDistinct(): void
    {
        $map = self::map();
        $map->set(true, 'yes');
        $map->set(false, 'no');

        self::assertSame(['yes', 'no'], $map->values());
        self::assertTrue($map->has(true, 'yes'));
        self::assertTrue($map->has(false, 'no'));
    }

    public function testObjectKeysUseIdentity(): void
    {
        $first  = new \stdClass;
        $second = new \stdClass;
        $map    = self::map();
        $map->set($first, 'first');
        $map->set($second, 'second');

        self::assertSame('first', $map->get($first));
        self::assertNull($map->get(clone $first));
        self::assertTrue($map->has($first));
        self::assertFalse($map->has(clone $first));

        self::assertTrue($map->delete($first));
        self::assertSame([$second], $map->keys());
        self::assertSame(['second'], $map->values());
    }

    public function testNanKeyIsEqualToItself(): void
    {
        $nan = \NAN;
        $map = self::map();
        $map->set($nan, 'not-a-number');

        self::assertTrue($map->has($nan));
        self::assertSame('not-a-number', $map->get($nan));
        self::assertTrue($map->delete($nan));
        self::assertTrue($map->isEmpty);
    }

    public function testArrayAndFloatKeysUseLinearLookup(): void
    {
        $arrayKey = ['nested' => true];
        $floatKey = 1.25;
        $map      = self::map();
        $map->set($arrayKey, 'array-value');
        $map->set($floatKey, 'float-value');

        self::assertSame('array-value', $map->get($arrayKey));
        self::assertSame('array-value', $map->get(['nested' => true]));
        self::assertNull($map->get(['nested' => false]));
        self::assertSame('float-value', $map->get(1.25));

        self::assertTrue($map->delete($arrayKey));
        self::assertTrue($map->has($floatKey));
        self::assertSame(['float-value'], $map->values());
    }

    public function testResourceKeyUsesLinearLookup(): void
    {
        $handle = \fopen('php://memory', 'r+');
        self::assertNotFalse($handle);

        try {
            $map = self::map();
            $map->set($handle, 'stream');

            self::assertSame('stream', $map->get($handle));
            self::assertTrue($map->delete($handle));
            self::assertTrue($map->isEmpty);
        }
        finally {
            \fclose($handle);
        }
    }

    public function testDeleteReindexesIndexedKeys(): void
    {
        $map = self::map([
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ]);

        self::assertTrue($map->delete('b'));
        self::assertSame(['a', 'c'], $map->keys());
        self::assertSame([1, 3], $map->values());
        self::assertTrue($map->has('a'));
        self::assertTrue($map->has('c'));
    }

    public function testMergeAssignAndClear(): void
    {
        $map = self::map(['first' => 1, 'second' => 2]);

        $map->merge(['first' => 10, 'third' => 3], override: false);
        self::assertSame(
            [
                ['key' => 'first', 'value' => 1],
                ['key' => 'second', 'value' => 2],
                ['key' => 'third', 'value' => 3],
            ],
            $map->all(),
        );

        $map->merge(['first' => 10, 'fourth' => 4]);
        self::assertSame(['first', 'second', 'third', 'fourth'], $map->keys());
        self::assertSame([10, 2, 3, 4], $map->values());

        $map->assign(['replacement' => 99]);
        self::assertSame(['replacement' => 99], \iterator_to_array($map));

        $map->clear();
        self::assertSame(0, $map->size);
        self::assertTrue($map->isEmpty);
        self::assertNull($map->first);
        self::assertNull($map->last);
    }

    public function testEveryAndAnyPassValueThenKey(): void
    {
        $map = self::map(['a' => 1, 'b' => 2]);

        $everySeen = [];
        self::assertTrue(
            $map->every(static function(
                mixed $value,
                mixed $key,
            ) use (&$everySeen): bool {
                $everySeen[] = [$value, $key];

                return true;
            }),
        );
        self::assertSame([[1, 'a'], [2, 'b']], $everySeen);

        self::assertTrue(
            $map->any(static fn(mixed $value, mixed $key): bool => $key === 'b' && $value === 2),
        );
        self::assertFalse(
            $map->any(static fn(mixed $value): bool => $value > 2),
        );
    }

    public function testArrayAccessAndJsonSerialize(): void
    {
        $map         = self::map(['a' => 1]);
        $map['b']    = 2;
        $map['null'] = null;

        self::assertTrue(isset($map['a']));
        self::assertFalse(isset($map['missing']));
        self::assertSame(1, $map['a']);
        self::assertNull($map['missing']);

        unset($map['a']);
        self::assertFalse(isset($map['a']));

        self::assertSame(
            [
                ['key' => 'b', 'value' => 2],
                ['key' => 'null', 'value' => null],
            ],
            $map->jsonSerialize(),
        );
        self::assertSame(
            '[{"key":"b","value":2},{"key":"null","value":null}]',
            \json_encode($map, \JSON_THROW_ON_ERROR),
        );
    }

    /** @noinspection PhpDeprecatedSincePhp85Inspection */
    public function testOffsetSetWithNullKeyThrows(): void
    {
        $map = self::map();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Map entries require a key; use set($key, $value).');

        $map[null] = 'value';
    }

    public function testCopyIsIndependent(): void
    {
        $map  = self::map(['a' => 1, 'b' => 2]);
        $copy = $map->copy();
        $copy->set('a', 10);
        $copy->delete('b');

        self::assertSame(['a' => 1, 'b' => 2], \iterator_to_array($map));
        self::assertSame(['a' => 10], \iterator_to_array($copy));
    }

    public function testConstructorAcceptsIterable(): void
    {
        $entries = static function(): \Generator {
            yield 'from-generator' => 'value';
            yield 0 => 'zero';
        };

        $map = new Map($entries());

        self::assertSame(['from-generator', 0], $map->keys());
        self::assertSame(['value', 'zero'], $map->values());
    }

    /**
     * @param iterable<mixed, mixed> $entries
     *
     * @return Map<mixed, mixed>
     */
    private static function map(
        iterable $entries = [],
    ): Map {
        return new Map($entries);
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use PHPUnit\Framework\TestCase;

final class SortScalarTest extends TestCase
{
    public function testNonArrayPassthrough(): void
    {
        self::assertSame('hello', \sort_keys('hello'));
        self::assertSame(42, \sort_keys(42));
        self::assertNull(\sort_keys(null));
    }

    public function testSortsAssociativeKeys(): void
    {
        self::assertSame(
            ['a' => 1, 'b' => 2],
            \sort_keys(['b' => 2, 'a' => 1]),
        );
    }

    public function testPreservesListOrder(): void
    {
        self::assertSame([3, 1, 2], \sort_keys([3, 1, 2]));
    }

    public function testRecursesIntoNestedArrays(): void
    {
        self::assertSame(
            [
                'a' => 0,
                'z' => ['a' => 1, 'b' => 2],
            ],
            \sort_keys([
                'z' => ['b' => 2, 'a' => 1],
                'a' => 0,
            ]),
        );
    }

    public function testRecursesIntoListItems(): void
    {
        self::assertSame(
            [
                ['a' => 1, 'b' => 2],
                ['c' => 3],
            ],
            \sort_keys([
                ['b' => 2, 'a' => 1],
                ['c' => 3],
            ]),
        );
    }

    public function testSortValuesNonArrayPassthrough(): void
    {
        self::assertSame('hello', \sort_values('hello'));
        self::assertSame(42, \sort_values(42));
        self::assertNull(\sort_values(null));
    }

    public function testSortValuesSortsLists(): void
    {
        self::assertSame([1, 2, 3], \sort_values([3, 1, 2]));
    }

    public function testSortValuesPreservesAssociativeKeyOrder(): void
    {
        self::assertSame(
            ['b' => 2, 'a' => 1],
            \sort_values(['b' => 2, 'a' => 1]),
        );
    }

    public function testSortValuesRecursesIntoNestedLists(): void
    {
        self::assertSame(
            [
                'z' => [1, 2, 3],
                'a' => [8, 9],
            ],
            \sort_values([
                'z' => [3, 1, 2],
                'a' => [9, 8],
            ]),
        );
    }

    public function testSortValuesNormalizesChildrenBeforeSortingLists(): void
    {
        self::assertSame(
            [
                [1, 2],
                [3, 4],
            ],
            \sort_values([
                [4, 3],
                [2, 1],
            ]),
        );
    }
}

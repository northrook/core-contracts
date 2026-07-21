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
}

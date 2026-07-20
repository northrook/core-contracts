<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\Contracts\get_checksum;

final class GetChecksumTest extends TestCase
{
    /**
     * Vectors shared with `northrook/hasher` Hash::checksum().
     *
     * @return iterable<string, array{string, string}>
     */
    public static function provideKnownValues(): iterable
    {
        yield 'empty' => ['', '001CRQ85'];
        yield 'hello' => ['hello', '03XG0XZS'];
        yield 'café' => ['café', '00ZXTEE1'];
        yield 'binary' => ["\x00\xff", '00FPQTR4'];
    }

    #[DataProvider('provideKnownValues')]
    public function testKnownVectors(
        string $input,
        string $expected,
    ): void {
        self::assertSame($expected, get_checksum($input));
    }

    public function testLengthAndCharset(): void
    {
        $checksum = get_checksum('hello');

        self::assertSame(8, \strlen($checksum));
        self::assertSame(8, \strspn($checksum, \CROCKFORD_BASE32));
    }

    public function testDeterministic(): void
    {
        self::assertSame(get_checksum('hello'), get_checksum('hello'));
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\JSON;
use Northrook\Contracts\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonTest extends TestCase
{
    public function testEncodeDefaultsUnescapeUnicodeSlashesAndPreserveFraction(): void
    {
        self::assertSame(
            '{"e":"é","u":"a/b","f":1.0}',
            JSON::encode(['e' => 'é', 'u' => 'a/b', 'f' => 1.0]),
        );
    }

    /**
     * @param array<string, bool> $options
     */
    #[DataProvider('provideEncodeOptions')]
    public function testEncodeOptions(
        array  $options,
        string $expected,
    ): void {
        self::assertSame($expected, JSON::encode('é/x', ...$options));
    }

    /**
     * @return \Generator<string, array{0: array<string, bool>, 1: string}>
     */
    public static function provideEncodeOptions(): \Generator
    {
        yield 'defaults' => [[], '"é/x"'];
        yield 'escape unicode' => [['escapeUnicode' => true], '"\u00e9/x"'];
        yield 'escape slashes' => [['escapeSlashes' => true], '"é\/x"'];
        yield 'escape both' => [
            ['escapeUnicode' => true, 'escapeSlashes' => true],
            '"\u00e9\/x"',
        ];
    }

    public function testEncodeWithoutPreserveZeroFraction(): void
    {
        self::assertSame('1', JSON::encode(1.0, preserveZeroFraction: false));
    }

    public function testEncodePretty(): void
    {
        self::assertSame(
            "{\n    \"a\": 1\n}",
            JSON::encode(['a' => 1], pretty: true),
        );
    }

    public function testEncodeFormatterAppliedToResult(): void
    {
        self::assertSame(
            '<{"a":1}>',
            JSON::encode(
                ['a' => 1],
                formatter: static fn(string $json): string => "<{$json}>",
            ),
        );
    }

    public function testEncodeInvalidUtf8ThrowsWrapped(): void
    {
        try {
            JSON::encode("\xB1\x31");
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    public function testEncodeInvalidUtf8Substitute(): void
    {
        self::assertSame('"' . "\u{FFFD}" . '1"', JSON::encode("\xB1\x31", invalidUtf8Substitute: true));
    }

    public function testEncodeReturnsFalseWithoutThrowAndSkipsFormatter(): void
    {
        self::assertFalse(JSON::encode("\xB1\x31", throwOnError: false));

        self::assertFalse(JSON::encode(
            "\xB1\x31",
            throwOnError: false,
            formatter: static fn(string $json): string => "<{$json}>",
        ));
    }

    #[DataProvider('provideDecodeScalars')]
    public function testDecodeScalars(
        string $json,
        mixed  $expected,
    ): void {
        self::assertSame($expected, JSON::decode($json));
    }

    /**
     * @return \Generator<string, array{0: string, 1: mixed}>
     */
    public static function provideDecodeScalars(): \Generator
    {
        yield 'integer' => ['123', 123];
        yield 'string' => ['"abc"', 'abc'];
        yield 'float' => ['1.5', 1.5];
        yield 'true' => ['true', true];
        yield 'null' => ['null', null];
    }

    public function testDecodeAssociativeByDefault(): void
    {
        self::assertSame(['a' => 1, 'b' => ['c' => true]], JSON::decode('{"a":1,"b":{"c":true}}'));
    }

    public function testDecodeObjectWhenNotAssociative(): void
    {
        $decoded = JSON::decode('{"a":1}', associative: false);

        self::assertInstanceOf(\stdClass::class, $decoded);
        self::assertSame(1, $decoded->a);
    }

    public function testDecodeBigintAsString(): void
    {
        self::assertSame('12345678901234567890', JSON::decode('12345678901234567890', bigintAsString: true));
        self::assertSame(1.2345678901234568E+19, JSON::decode('12345678901234567890'));
    }

    public function testDecodeInvalidThrowsWrapped(): void
    {
        try {
            JSON::decode('{invalid');
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    public function testDecodeInvalidReturnsNullWithoutThrow(): void
    {
        self::assertNull(JSON::decode('{invalid', throwOnError: false));
    }

    public function testDecodeDepthExceededReturnsNullWithoutThrow(): void
    {
        self::assertNull(JSON::decode('[["too deep"]]', throwOnError: false, depth: 2));
    }

    public function testDecodeDepthExceededThrows(): void
    {
        $this->expectException(RuntimeException::class);

        JSON::decode('[["too deep"]]', depth: 2);
    }
}

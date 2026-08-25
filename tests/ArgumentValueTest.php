<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Argument\Value;
use Northrook\Filesystem\Directory;
use Northrook\Filesystem\File;
use Northrook\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArgumentValueTest extends TestCase
{
    #[DataProvider('provideMatches')]
    public function testMatches(
        Value $case,
        mixed $value,
        bool  $expected,
    ): void {
        self::assertSame($expected, $case->matches($value));
    }

    public static function provideMatches(): \Generator
    {
        yield 'unset never' => [Value::Unset, 'x', false];
        yield 'unset null' => [Value::Unset, null, false];
        yield 'null' => [Value::Null, null, true];
        yield 'null rejects 0' => [Value::Null, 0, false];
        yield 'bool true' => [Value::Bool, true, true];
        yield 'bool false' => [Value::Bool, false, true];
        yield 'bool rejects 1' => [Value::Bool, 1, false];
        yield 'true' => [Value::True, true, true];
        yield 'true rejects false' => [Value::True, false, false];
        yield 'false' => [Value::False, false, true];
        yield 'int' => [Value::Int, 0, true];
        yield 'int rejects float' => [Value::Int, 1.0, false];
        yield 'float' => [Value::Float, 1.5, true];
        yield 'number int' => [Value::Number, 2, true];
        yield 'number float' => [Value::Number, 2.5, true];
        yield 'number numeric string' => [Value::Number, '3.14', true];
        yield 'number rejects bool' => [Value::Number, true, false];
        yield 'string' => [Value::String, '', true];
        yield 'non-empty string' => [Value::NonEmptyString, 'x', true];
        yield 'non-empty string rejects empty' => [Value::NonEmptyString, '', false];
        yield 'non-empty string rejects non-string' => [Value::NonEmptyString, 1, false];
        yield 'non-empty lowercase' => [Value::NonEmptyLowercaseString, 'a-b_1', true];
        yield 'non-empty lowercase rejects empty' => [Value::NonEmptyLowercaseString, '', false];
        yield 'non-empty lowercase rejects mixed case' => [Value::NonEmptyLowercaseString, 'Abc', false];
        yield 'class-string' => [Value::ClassString, \stdClass::class, true];
        yield 'class-string rejects empty' => [Value::ClassString, '', false];
        yield 'array' => [Value::Array, [], true];
        yield 'object' => [Value::Object, new \stdClass, true];
        yield 'key string' => [Value::Key, 'a', true];
        yield 'key int' => [Value::Key, 0, true];
        yield 'key rejects float' => [Value::Key, 1.0, false];
    }

    public function testProvided(): void
    {
        self::assertFalse(Value::provided(Value::String));
        self::assertTrue(Value::provided('x'));
        self::assertTrue(Value::provided(null));
    }

    public function testRequireReturnsValue(): void
    {
        self::assertSame('id', Value::String->require('id', 'id'));
        self::assertSame(1, Value::Unset->require(1, 'n'));
        self::assertNull(Value::Null->require(null, 'x'));
    }

    public function testRequireMissingPlaceholder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required argument `id` is missing.');
        Value::String->require(Value::String, 'id');
    }

    public function testRequireWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid argument `n`: expected Int, got');
        Value::Int->require('1', 'n');
    }

    public function testOptionalDefaultOnPlaceholder(): void
    {
        self::assertSame('fallback', Value::String->optional(Value::String, 'fallback'));
        self::assertNull(Value::Int->optional(Value::Unset));
    }

    public function testOptionalReturnsProvided(): void
    {
        self::assertSame('ok', Value::String->optional('ok', 'fallback'));
    }

    public function testOptionalRejectsWrongType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Value::Int->optional('no', 0);
    }

    public function testPathFileDirectoryRequireExisting(): void
    {
        $file = File::temporary();
        $dir  = Directory::temporary();

        try {
            self::assertTrue(Value::Path->matches($file));
            self::assertTrue(Value::Path->matches($file->value));
            self::assertTrue(Value::Path->matches($dir));
            self::assertTrue(Value::File->matches($file));
            self::assertTrue(Value::File->matches($file->value));
            self::assertTrue(Value::Directory->matches($dir));
            self::assertTrue(Value::Directory->matches($dir->value));

            self::assertFalse(Value::File->matches($dir->value));
            self::assertFalse(Value::Directory->matches($file->value));
            self::assertFalse(Value::Path->matches($dir->value . '/no-such-' . \bin2hex(\random_bytes(4))));
            self::assertFalse(Value::File->matches(''));
            self::assertFalse(Value::Path->matches('https://example.com/x'));
            self::assertFalse(Value::Path->matches(1));
        }
        finally {
            @\unlink($file->value);
        }
    }
}

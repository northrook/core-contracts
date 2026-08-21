<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Filesystem\Directory;
use Northrook\Filesystem\File;
use Northrook\Filesystem\Path;
use Northrook\InvalidArgumentException;
use Northrook\Parameter\Type;
use Northrook\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

enum ParameterTypeUnitFixture
{
    case Alpha;
}

enum ParameterTypeIntBackedFixture: int
{
    case One = 1;
}

final class ParameterTypeTest extends TestCase
{
    #[DataProvider('provideValueAccepts')]
    public function testValueAccepts(
        mixed $value,
    ): void {
        self::assertTrue(Type::Value->validate($value));
    }

    public static function provideValueAccepts(): \Generator
    {
        yield 'null' => [null];
        yield 'bool' => [true];
        yield 'int' => [0];
        yield 'float' => [1.5];
        yield 'string' => ['ok'];
        yield 'empty array' => [[]];
        yield 'list' => [[1, 'a', false]];
        yield 'keyed' => [['a' => 1, 'b' => [true]]];
        yield 'unit enum' => [ParameterTypeUnitFixture::Alpha];
        yield 'backed enum' => [ParameterTypeIntBackedFixture::One];
        yield 'depth 5' => [[[[[[true]]]]]];
    }

    #[DataProvider('provideValueRejects')]
    public function testValueRejects(
        mixed $value,
    ): void {
        self::assertFalse(Type::Value->validate($value));
    }

    public static function provideValueRejects(): \Generator
    {
        yield 'stdClass' => [new \stdClass];
        yield 'closure' => [static fn() => null];
        yield 'nested object' => [[1, new \stdClass]];
        yield 'Path object' => [new Path('var/cache')];
        yield 'File object' => [new File('app.ini')];
        yield 'Directory object' => [new Directory('var/cache')];
    }

    public function testValueOverflowThrows(): void
    {
        $this->expectException(RuntimeException::class);
        Type::Value->validate([[[[[[true]]]]]]);
    }

    #[DataProvider('providePathAccepts')]
    public function testPathAcceptsNonEmptyStrings(
        string $value,
    ): void {
        self::assertTrue(Type::Path->validate($value));
    }

    public static function providePathAccepts(): \Generator
    {
        yield 'relative dir' => ['var/cache'];
        yield 'absolute dir' => ['/var/cache'];
        yield 'trailing sep' => ['/var/cache' . \DIR_SEP];
        yield 'file' => ['php/contracts/runtime/Runtime/Assert.php'];
        yield 'dotfile' => ['.env'];
        yield 'root' => [\DIR_SEP];
        yield 'dot' => ['.'];
        yield 'dotdot' => ['..'];
    }

    public function testPathAcceptsFilesystemObjects(): void
    {
        self::assertTrue(Type::Path->validate(new Path('var/cache')));
        self::assertTrue(Type::Path->validate(new File('app.ini')));
        self::assertTrue(Type::Path->validate(new Directory('var/cache')));
    }

    #[DataProvider('provideNonEmptyStringRejects')]
    public function testPathRejectsNonEmptyString(
        mixed $value,
    ): void {
        self::assertFalse(Type::Path->validate($value));
    }

    public static function provideNonEmptyStringRejects(): \Generator
    {
        yield 'empty' => [''];
        yield 'null' => [null];
        yield 'int' => [1];
        yield 'array' => [['/var']];
    }

    #[DataProvider('provideDirectoryAccepts')]
    public function testDirectoryAcceptsExtensionlessPaths(
        string $value,
    ): void {
        self::assertTrue(Type::Directory->validate($value));
    }

    public static function provideDirectoryAccepts(): \Generator
    {
        yield 'relative' => ['var/cache'];
        yield 'absolute' => ['/usr/bin'];
        yield 'trailing sep' => ['/var/cache' . \DIR_SEP];
        yield 'root' => [\DIR_SEP];
        yield 'dot' => ['.'];
        yield 'dotdot' => ['..'];
        yield 'extensionless file name' => ['Dockerfile'];
        yield 'nested relative' => ['php/contracts/runtime'];
    }

    public function testDirectoryAcceptsDirectoryObject(): void
    {
        self::assertTrue(Type::Directory->validate(new Directory('var/cache')));
    }

    #[DataProvider('provideDirectoryRejects')]
    public function testDirectoryRejectsFileShapes(
        mixed $value,
    ): void {
        self::assertFalse(Type::Directory->validate($value));
    }

    public static function provideDirectoryRejects(): \Generator
    {
        yield 'php file' => ['Assert.php'];
        yield 'nested file' => ['php/contracts/runtime/Runtime/Assert.php'];
        yield 'dotfile' => ['.env'];
        yield 'double extension' => ['archive.tar.gz'];
        yield 'empty' => [''];
        yield 'non-string' => [1];
        yield 'Path object' => [new Path('var/cache')];
        yield 'File object' => [new File('app.ini')];
    }

    #[DataProvider('provideFileAccepts')]
    public function testFileAcceptsExtensionPaths(
        string $value,
    ): void {
        self::assertTrue(Type::File->validate($value));
    }

    public static function provideFileAccepts(): \Generator
    {
        yield 'php' => ['Assert.php'];
        yield 'nested' => ['php/contracts/runtime/Runtime/Assert.php'];
        yield 'absolute' => ['/etc/app.ini'];
        yield 'dotfile' => ['.env'];
        yield 'gitignore' => ['.gitignore'];
        yield 'double extension' => ['archive.tar.gz'];
        yield 'minified' => ['app.min.js'];
    }

    public function testFileAcceptsFileObject(): void
    {
        self::assertTrue(Type::File->validate(new File('app.ini')));
    }

    #[DataProvider('provideFileRejects')]
    public function testFileRejectsDirectoryShapes(
        mixed $value,
    ): void {
        self::assertFalse(Type::File->validate($value));
    }

    public static function provideFileRejects(): \Generator
    {
        yield 'relative dir' => ['var/cache'];
        yield 'absolute dir' => ['/usr/bin'];
        yield 'trailing sep' => ['/var/cache' . \DIR_SEP];
        yield 'trailing sep on file name' => ['Assert.php' . \DIR_SEP];
        yield 'root' => [\DIR_SEP];
        yield 'dot' => ['.'];
        yield 'dotdot' => ['..'];
        yield 'Dockerfile' => ['Dockerfile'];
        yield 'empty' => [''];
        yield 'non-string' => [false];
        yield 'Path object' => [new Path('app.ini')];
        yield 'Directory object' => [new Directory('var/cache')];
    }

    public function testSettingAcceptsScalarsAndEnums(): void
    {
        self::assertTrue(Type::Setting->validate(true));
        self::assertTrue(Type::Setting->validate(1));
        self::assertTrue(Type::Setting->validate(1.5));
        self::assertTrue(Type::Setting->validate('en'));
        self::assertTrue(Type::Setting->validate(ParameterTypeUnitFixture::Alpha));
    }

    public function testSettingRejectsNullAndArrays(): void
    {
        self::assertFalse(Type::Setting->validate(null));
        self::assertFalse(Type::Setting->validate([]));
        self::assertFalse(Type::Setting->validate(['en']));
        self::assertFalse(Type::Setting->validate(new \stdClass));
    }

    #[DataProvider('provideResolveCases')]
    public function testResolve(
        mixed $value,
        Type  $expected,
    ): void {
        self::assertSame($expected, Type::resolve($value));
    }

    public static function provideResolveCases(): \Generator
    {
        yield 'null' => [null, Type::Value];
        yield 'string' => ['ok', Type::Value];
        yield 'array' => [[1], Type::Value];
        yield 'unit enum' => [ParameterTypeUnitFixture::Alpha, Type::Value];
        yield 'stringable' => [
            new class implements \Stringable {
                public function __toString(): string
                {
                    return 'ok';
                }
            },
            Type::Value,
        ];
        yield 'Path' => [new Path('var/cache'), Type::Path];
        yield 'File' => [new File('app.ini'), Type::File];
        yield 'Directory' => [new Directory('var/cache'), Type::Directory];
    }

    public function testResolveRejectsUnsupportedObjects(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Type::resolve(new \stdClass);
    }

    #[DataProvider('provideFromAccepts')]
    public function testFromAccepts(
        string|Type $value,
        Type        $expected,
    ): void {
        self::assertSame($expected, Type::from($value));
    }

    public static function provideFromAccepts(): \Generator
    {
        yield 'case name' => ['path', Type::Path];
        yield 'case name uppercase' => ['DIRECTORY', Type::Directory];
        yield 'fqcn case' => [Type::class . '::File', Type::File];
        yield 'instance' => [Type::Setting, Type::Setting];
    }

    public function testFromRejectsUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Type::from('not-a-type');
    }

    public function testTryFromReturnsNullForUnknown(): void
    {
        self::assertNull(Type::tryFrom('not-a-type'));
        self::assertNull(Type::tryFrom(''));
        self::assertSame(Type::Value, Type::tryFrom('value'));
    }
}

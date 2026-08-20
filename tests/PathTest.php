<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Filesystem\Directory;
use Northrook\Filesystem\File;
use Northrook\Filesystem\Path;
use Northrook\InvalidArgumentException;
use Northrook\Reference\Uri;
use Northrook\RuntimeException;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function testNormalizesOnConstruction(): void
    {
        self::assertSame('a/b/c', new Path('a//b/./c')->value);
        self::assertSame('a/b/c', (string) new Path('a//b/./c'));
        self::assertSame('a/b/c', Path::normalize('a//b/./c'));
    }

    public function testRejectsEmpty(): void
    {
        self::assertFalse(Path::isValid(''));
        self::assertNull(Path::from(''));

        $this->expectException(InvalidArgumentException::class);
        new Path('');
    }

    public function testRejectsUriShapedPaths(): void
    {
        self::assertFalse(Path::isValid('file:///tmp/x'));
        self::assertFalse(Path::isValid('https://example.com/x'));

        $this->expectException(InvalidArgumentException::class);
        new Path('file:///tmp/x');
    }

    public function testStructure(): void
    {
        $path = new Path('/var/log/app.log');

        self::assertSame('/var/log/app.log', $path->pathname());
        self::assertSame('/var/log', $path->dirname());
        self::assertSame('app.log', $path->basename());
        self::assertSame('app', $path->filename());
        self::assertSame('log', $path->extension());
    }

    public function testStructureOfDotfile(): void
    {
        $path = new Path('/x/.gitignore');

        // pathinfo() treats the leading-dot name as the extension.
        self::assertSame('.gitignore', $path->basename());
        self::assertSame('', $path->filename());
        self::assertSame('gitignore', $path->extension());
    }

    public function testJoin(): void
    {
        $path = new Path('/a');

        self::assertSame('/a/b/c', $path->join('b', '', 'c')->value);
        self::assertSame('/a', $path->value);
    }

    public function testParentReturnsDirectory(): void
    {
        $parent = new Path('/var/log/app.log')->parent();

        self::assertInstanceOf(Directory::class, $parent);
        self::assertSame('/var/log', $parent->value);
    }

    public function testWithExtensionAcceptsLeadingDot(): void
    {
        $path = new Path('/home/user/file.txt');

        self::assertSame('/home/user/file.bak', $path->withExtension('.bak')->value);
    }

    public function testPredicates(): void
    {
        self::assertTrue(new Path('/x/y')->isAbsolute());
        self::assertFalse(new Path('/x/y')->isRelative());
        self::assertTrue(new Path('x/y')->isRelative());
        self::assertFalse(new Path('x/y')->isAbsolute());
        self::assertTrue(new Path('/x/.hidden')->isDot());
        self::assertFalse(new Path('/x/visible')->isDot());
    }

    public function testEquals(): void
    {
        $path = new Path('/a/b');

        self::assertTrue($path->equals(new Path('/a/b')));
        self::assertTrue($path->equals('/a//b'));
        self::assertFalse($path->equals('/a/c'));
        self::assertFalse($path->equals(''));
    }

    public function testTypedViews(): void
    {
        $path = new Path('/x/y.txt');

        $file = $path->asFile();
        self::assertInstanceOf(File::class, $file);
        self::assertSame('/x/y.txt', $file->value);

        $directory = $path->asDirectory();
        self::assertInstanceOf(Directory::class, $directory);
        self::assertSame('/x/y.txt', $directory->value);

        $generic = $path->toPath();
        self::assertInstanceOf(Path::class, $generic);
        self::assertSame('/x/y.txt', $generic->value);
    }

    public function testToUriBuildsFileUri(): void
    {
        $uri = new Path('/tmp/x')->toUri();

        self::assertInstanceOf(Uri::class, $uri);
        self::assertSame('file:///tmp/x', $uri->value);
        self::assertTrue($uri->isFile());
    }

    public function testAbsolute(): void
    {
        $absolute = new Path('foo/bar')->absolute();

        self::assertSame(\getcwd() . '/foo/bar', $absolute->value);
        self::assertTrue($absolute->isAbsolute());
    }

    public function testRelativeTo(): void
    {
        self::assertSame('b/c', new Path('/a/b/c')->relativeTo('/a')->value);
        self::assertSame('.', new Path('/a/b')->relativeTo('/a/b')->value);
        self::assertSame('..', new Path('/a')->relativeTo('/a/b')->value);
    }

    public function testExistsAndTypePredicatesOnDisk(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'nr-path-');

        self::assertIsString($file);

        try {
            $path = new Path($file);

            self::assertTrue($path->exists());
            self::assertTrue($path->isFile());
            self::assertFalse($path->isDirectory());
            self::assertFalse($path->isLink());
            self::assertTrue($path->isReadable());
            self::assertTrue($path->isWritable());

            self::assertFalse(new Path($file . '.missing')->exists());
        } finally {
            @\unlink($file);
        }
    }

    public function testExistsThrowOnError(): void
    {
        $this->expectException(RuntimeException::class);
        new Path('/definitely/missing/nr-path')->exists(throwOnError: true);
    }

    public function testRealPath(): void
    {
        $file = \tempnam(\sys_get_temp_dir(), 'nr-path-');

        self::assertIsString($file);

        try {
            self::assertSame(\realpath($file), new Path($file)->realPath());
            self::assertFalse(new Path($file . '.missing')->realPath());
        } finally {
            @\unlink($file);
        }
    }

    public function testRealPathThrow(): void
    {
        $this->expectException(RuntimeException::class);
        new Path('/definitely/missing/nr-path')->realPath(throw: true);
    }

    public function testGlobAndRemove(): void
    {
        $base = \sys_get_temp_dir() . '/nr-path-glob-' . \bin2hex(\random_bytes(4));
        \mkdir($base);
        \file_put_contents($base . '/one.txt', '1');
        \file_put_contents($base . '/two.txt', '2');
        \file_put_contents($base . '/three.log', '3');

        try {
            $matches = new Path($base)->glob('*.txt');
            $names   = \array_map(static fn(Path $path): string => $path->basename(), $matches);
            \sort($names);

            self::assertSame(['one.txt', 'two.txt'], $names);

            $file = new Path($base . '/three.log');
            self::assertTrue($file->remove());
            self::assertFileDoesNotExist($base . '/three.log');
        } finally {
            @\unlink($base . '/one.txt');
            @\unlink($base . '/two.txt');
            @\unlink($base . '/three.log');
            @\rmdir($base);
        }
    }

    public function testMoveOverwriteUnlinksSymlinkToDirectoryWithoutWipingTarget(): void
    {
        $base = \sys_get_temp_dir() . '/nr-path-move-link-' . \bin2hex(\random_bytes(4));
        \mkdir($base . '/real', 0777, true);
        \file_put_contents($base . '/real/keep.txt', 'keep');
        \file_put_contents($base . '/src.txt', 'src');
        self::assertTrue(\symlink($base . '/real', $base . '/linkdir'));

        try {
            $moved = new Path($base . '/src.txt')->move($base . '/linkdir', overwrite: true);

            self::assertSame($base . '/linkdir', $moved->value);
            self::assertFileExists($base . '/linkdir');
            self::assertFalse(\is_link($base . '/linkdir'));
            self::assertFileDoesNotExist($base . '/src.txt');
            self::assertFileExists($base . '/real/keep.txt');
            self::assertSame('keep', \file_get_contents($base . '/real/keep.txt'));
            self::assertSame('src', \file_get_contents($base . '/linkdir'));
        } finally {
            @\unlink($base . '/linkdir');
            @\unlink($base . '/src.txt');
            @\unlink($base . '/real/keep.txt');
            @\rmdir($base . '/real');
            @\rmdir($base);
        }
    }

    public function testWithExtensionAtFilesystemRoot(): void
    {
        $path = new Path('/file.txt');

        self::assertSame('/file.bak', $path->withExtension('bak')->value);
        self::assertSame('/file', $path->withExtension('')->value);
    }

    public function testWithBasenameAtFilesystemRoot(): void
    {
        $path = new Path('/file.txt');

        self::assertSame('/other.txt', $path->withBasename('other.txt')->value);
    }

    public function testWithExtensionUnderNestedDir(): void
    {
        $path = new Path('/home/user/file.txt');

        self::assertSame('/home/user/file.bak', $path->withExtension('bak')->value);
    }
}

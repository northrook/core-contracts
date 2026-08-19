<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Filesystem\FilesystemException;
use Northrook\Filesystem\NativeFilesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NativeFilesystem::class)]
final class NativeFilesystemTest extends TestCase
{
    /** @var non-empty-string */
    private string $root;

    private NativeFilesystem $fs;

    protected function setUp(): void
    {
        $this->fs   = new NativeFilesystem;
        $this->root = \sys_get_temp_dir() . '/nr-native-fs-' . \bin2hex(\random_bytes(4));
        \mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->root);
    }

    public function testPredicates(): void
    {
        $file = $this->root . '/a.txt';
        \file_put_contents($file, 'x');

        self::assertTrue($this->fs->fileExists($file));
        self::assertTrue($this->fs->isFile($file));
        self::assertFalse($this->fs->isDirectory($file));
        self::assertTrue($this->fs->isReadable($file));
        self::assertTrue($this->fs->isDirectory($this->root));
        self::assertTrue($this->fs->isAbsolutePath($this->root));
    }

    public function testOverlongPathThrows(): void
    {
        $this->expectException(FilesystemException::class);

        $this->fs->fileExists(\str_repeat('a', \MAX_PATH_LENGTH + 1));
    }

    public function testReadWriteAppend(): void
    {
        $file = $this->root . '/io.txt';

        $this->fs->writeFileAtomically($file, 'hello');
        self::assertSame('hello', $this->fs->readFile($file));

        $this->fs->appendToFile($file, ' world');
        self::assertSame('hello world', $this->fs->readFile($file));
        self::assertSame(11, $this->fs->fileSize($file));
    }

    public function testCreateDirectoryAndList(): void
    {
        $dir = $this->root . '/nested/child';
        $this->fs->createDirectory($dir);

        self::assertTrue($this->fs->isDirectory($dir));

        $listed = $this->fs->listDirectory($this->root . '/nested');
        self::assertContains($dir, $listed);
    }

    public function testCopyFile(): void
    {
        $source = $this->root . '/src.txt';
        $target = $this->root . '/dst.txt';
        \file_put_contents($source, 'copy-me');

        $this->fs->copyFile($source, $target, alwaysOverwrite: true);

        self::assertSame('copy-me', \file_get_contents($target));
    }

    public function testRemoveRecursive(): void
    {
        $nested = $this->root . '/tree/a/b.txt';
        $this->fs->createParentDirectory($nested);
        \file_put_contents($nested, 'x');

        $this->fs->remove($this->root . '/tree');

        self::assertFalse(\file_exists($this->root . '/tree'));
    }

    public function testSyncDirectorySmoke(): void
    {
        $source = $this->root . '/sync-src';
        $dest   = $this->root . '/sync-dest';
        \mkdir($source);
        \file_put_contents($source . '/one.txt', '1');

        $this->fs->syncDirectory($source, $dest);

        self::assertSame('1', \file_get_contents($dest . '/one.txt'));

        \file_put_contents($source . '/two.txt', '2');
        \unlink($source . '/one.txt');

        $this->fs->syncDirectory($source, $dest, deleteMissingFiles: true);

        self::assertFileDoesNotExist($dest . '/one.txt');
        self::assertSame('2', \file_get_contents($dest . '/two.txt'));
    }

    public function testCreateTemporaryFileWithSuffix(): void
    {
        $path = $this->fs->createTemporaryFile($this->root, 'tmp_', '.dat');

        self::assertFileExists($path);
        self::assertStringEndsWith('.dat', $path);
        self::assertStringStartsWith($this->root, $path);
    }

    public function testMimeTypeAndTimes(): void
    {
        $file = $this->root . '/meta.txt';
        \file_put_contents($file, 'plain');

        self::assertIsInt($this->fs->modifiedTime($file));
        self::assertIsInt($this->fs->createdTime($file));
        self::assertNotNull($this->fs->mimeType($file));
    }

    public function testMove(): void
    {
        $source = $this->root . '/from.txt';
        $target = $this->root . '/to.txt';
        \file_put_contents($source, 'moved');

        $this->fs->move($source, $target);

        self::assertFileDoesNotExist($source);
        self::assertSame('moved', \file_get_contents($target));
    }
}

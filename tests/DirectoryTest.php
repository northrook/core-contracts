<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Directory;
use Northrook\Contracts\File;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\Path;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Tests\Support\FilesystemStub;
use PHPUnit\Framework\TestCase;

final class DirectoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = \sys_get_temp_dir() . '/nr-dir-test-' . \bin2hex(\random_bytes(6));

        \mkdir($this->root . '/sub/nested', 0777, true);
        \file_put_contents($this->root . '/a.txt', 'a');
        \file_put_contents($this->root . '/sub/b.txt', 'b');
        \file_put_contents($this->root . '/.hidden', 'h');
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->root)) {
            new Directory($this->root)->remove();
        }
    }

    public function testConstructionNormalizesPath(): void
    {
        $directory = new Directory($this->root . '//sub/../');

        self::assertSame($this->root, $directory->value);
        self::assertSame($this->root, (string) $directory);
    }

    public function testConstructionFromPathInstance(): void
    {
        $path      = new Path($this->root);
        $directory = new Directory($path);

        self::assertSame($this->root, $directory->value);
    }

    public function testAssertThrowsForMissingDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        new Directory($this->root . '/missing', assert: true);
    }

    public function testAssertThrowsForFilePath(): void
    {
        $this->expectException(RuntimeException::class);
        new Directory($this->root . '/a.txt', assert: true);
    }

    public function testAssertAcceptsExistingDirectory(): void
    {
        $directory = new Directory($this->root, assert: true);

        self::assertTrue($directory->isDirectory());
    }

    public function testTemporaryReturnsSystemTemp(): void
    {
        $temporary = Directory::temporary();

        self::assertSame(new Directory(\sys_get_temp_dir())->value, $temporary->value);
        self::assertTrue($temporary->isDirectory());
    }

    public function testPathView(): void
    {
        $path = new Directory($this->root)->path();

        self::assertInstanceOf(Path::class, $path);
        self::assertNotInstanceOf(Directory::class, $path);
        self::assertSame($this->root, $path->value);
    }

    public function testParentReturnsParentDirectory(): void
    {
        $nested = new Directory($this->root . '/sub/nested');
        $parent = $nested->parent();

        self::assertInstanceOf(Directory::class, $parent);
        self::assertSame($this->root . '/sub', $parent->value);
    }

    public function testChildFileDirectoryJoins(): void
    {
        $directory = new Directory($this->root);

        $child = $directory->child('x.txt');
        self::assertInstanceOf(Path::class, $child);
        self::assertSame($this->root . '/x.txt', $child->value);

        $file = $directory->file('y.txt');
        self::assertInstanceOf(File::class, $file);
        self::assertSame($this->root . '/y.txt', $file->value);

        $sub = $directory->directory('z');
        self::assertInstanceOf(Directory::class, $sub);
        self::assertSame($this->root . '/z', $sub->value);

        self::assertSame(
            $this->root . '/bar',
            $directory->child('foo/../bar')->value,
        );
        self::assertTrue($directory->contains($directory->child('foo/../bar')));
    }

    public function testChildRejectsEscape(): void
    {
        $directory = new Directory($this->root);

        $this->expectException(InvalidArgumentException::class);
        $directory->child('../outside');
    }

    public function testChildRejectsAbsoluteSegment(): void
    {
        $directory = new Directory($this->root);

        $this->expectException(InvalidArgumentException::class);
        $directory->file('/etc/passwd');
    }

    public function testChildRejectsEmptySegment(): void
    {
        $directory = new Directory($this->root);

        $this->expectException(InvalidArgumentException::class);
        $directory->directory('');
    }

    public function testExistsAndThrowOnError(): void
    {
        $directory = new Directory($this->root);
        $missing   = new Directory($this->root . '/missing');

        self::assertTrue($directory->exists());
        self::assertFalse($missing->exists());

        $this->expectException(RuntimeException::class);
        $missing->exists(throwOnError: true);
    }

    public function testIsEmpty(): void
    {
        $empty = $this->root . '/sub/nested';

        self::assertFalse(new Directory($this->root)->isEmpty());
        self::assertTrue(new Directory($empty)->isEmpty());
        self::assertFalse(new Directory($this->root . '/missing')->isEmpty());
    }

    public function testContains(): void
    {
        $directory = new Directory($this->root);

        self::assertTrue($directory->contains($this->root));
        self::assertTrue($directory->contains($this->root . '/sub'));
        self::assertTrue($directory->contains($this->root . '/sub/b.txt'));
        self::assertTrue($directory->contains(new Path($this->root . '/sub/nested')));
        self::assertFalse($directory->contains(\sys_get_temp_dir()));
        self::assertFalse($directory->contains($this->root . '/../outside'));
    }

    public function testEquals(): void
    {
        $directory = new Directory($this->root);

        self::assertTrue($directory->equals($this->root . '/'));
        self::assertTrue($directory->equals(new Directory($this->root)));
        self::assertFalse($directory->equals($this->root . '/sub'));
    }

    public function testChildrenSkipsDotsByDefault(): void
    {
        $children = new Directory($this->root)->children();
        $names    = \array_map(static fn(Path $path): string => $path->basename(), $children);

        self::assertContainsOnlyInstancesOf(Path::class, $children);
        self::assertCount(2, $children);
        self::assertContains('a.txt', $names);
        self::assertContains('sub', $names);
        self::assertNotContains('.hidden', $names);
    }

    public function testChildrenIncludesDotsOnRequest(): void
    {
        $children = new Directory($this->root)->children(includeDots: true);
        $names    = \array_map(static fn(Path $path): string => $path->basename(), $children);

        self::assertCount(3, $children);
        self::assertContains('.hidden', $names);
        self::assertContains('a.txt', $names);
        self::assertContains('sub', $names);
    }

    public function testFilesAndDirectories(): void
    {
        $directory = new Directory($this->root);

        $files = $directory->files();
        self::assertCount(1, $files);
        self::assertContainsOnlyInstancesOf(File::class, $files);
        self::assertSame($this->root . '/a.txt', $files[0]->value);

        $directories = $directory->directories();
        self::assertCount(1, $directories);
        self::assertContainsOnlyInstancesOf(Directory::class, $directories);
        self::assertSame($this->root . '/sub', $directories[0]->value);
    }

    public function testGlob(): void
    {
        $directory = new Directory($this->root);

        $matches = $directory->glob('*.txt');
        self::assertCount(1, $matches);
        self::assertSame($this->root . '/a.txt', $matches[0]->value);

        $nested = $directory->glob(['sub/*.txt', 'missing/*.txt']);
        self::assertCount(1, $nested);
        self::assertSame($this->root . '/sub/b.txt', $nested[0]->value);
    }

    public function testWalk(): void
    {
        $paths = [];
        foreach (new Directory($this->root)->walk() as $path) {
            $paths[] = $path->value;
        }

        // RecursiveIteratorIterator is leaves-only: directories are not yielded.
        self::assertContains($this->root . '/a.txt', $paths);
        self::assertContains($this->root . '/sub/b.txt', $paths);
        self::assertNotContains($this->root . '/sub/nested', $paths);
        self::assertNotContains($this->root . '/.hidden', $paths);
        self::assertCount(2, $paths);
    }

    public function testWalkIncludesDotsOnRequest(): void
    {
        $paths = [];
        foreach (new Directory($this->root)->walk(includeDots: true) as $path) {
            $paths[] = $path->value;
        }

        self::assertContains($this->root . '/.hidden', $paths);
    }

    public function testWalkOfMissingDirectoryYieldsNothing(): void
    {
        self::assertSame(
            [],
            \iterator_to_array(new Directory($this->root . '/missing')->walk()),
        );
    }

    public function testWalkDoesNotFollowSymlinks(): void
    {
        $link = $this->root . '/loop';
        self::assertTrue(\symlink($this->root, $link));

        $paths = [];
        foreach (new Directory($this->root)->walk() as $path) {
            $paths[] = $path->value;
        }

        self::assertContains($this->root . '/a.txt', $paths);
        self::assertContains($this->root . '/sub/b.txt', $paths);
        self::assertContains($link, $paths);
        self::assertTrue(new Path($link)->isLink());
        // No second pass through the tree via the ancestor link.
        self::assertCount(3, $paths);

        $stubPaths = [];
        foreach (new Directory($this->root, filesystem: new FilesystemStub)->walk() as $path) {
            $stubPaths[] = $path->value;
        }

        self::assertEqualsCanonicalizing($paths, $stubPaths);
    }

    public function testListingUsesFilesystemWhenPresent(): void
    {
        $stub      = new FilesystemStub;
        $directory = new Directory($this->root, filesystem: $stub);

        self::assertFalse($directory->isEmpty());
        self::assertContains('listDirectory', $stub->calls);

        $stub->calls = [];
        $children    = $directory->children();
        $names       = \array_map(static fn(Path $path): string => $path->basename(), $children);

        self::assertContains('listDirectory', $stub->calls);
        self::assertContains('a.txt', $names);
        self::assertContains('sub', $names);
        self::assertNotContains('.hidden', $names);

        $stub->calls = [];
        $paths       = [];
        foreach ($directory->walk() as $path) {
            $paths[] = $path->value;
        }

        self::assertContains('listDirectory', $stub->calls);
        self::assertContains($this->root . '/a.txt', $paths);
        self::assertContains($this->root . '/sub/b.txt', $paths);
        self::assertNotContains($this->root . '/sub/nested', $paths);
        self::assertNotContains($this->root . '/.hidden', $paths);
        self::assertCount(2, $paths);

        $paths = [];
        foreach ($directory->walk(includeDots: true) as $path) {
            $paths[] = $path->value;
        }

        self::assertContains($this->root . '/.hidden', $paths);
    }

    public function testIsEmptyUsesFilesystemWhenPresent(): void
    {
        $stub  = new FilesystemStub;
        $empty = new Directory($this->root . '/sub/nested', filesystem: $stub);

        self::assertTrue($empty->isEmpty());
        self::assertContains('listDirectory', $stub->calls);
    }

    public function testEnsureCreatesAndIsIdempotent(): void
    {
        $directory = new Directory($this->root . '/made/deep');

        self::assertFalse($directory->isDirectory());
        self::assertTrue($directory->ensure());
        self::assertTrue($directory->isDirectory());
        self::assertTrue($directory->ensure());
    }

    public function testMkdir(): void
    {
        $directory = new Directory($this->root . '/made');

        self::assertTrue($directory->mkdir());
        self::assertTrue($directory->isDirectory());
        self::assertTrue($directory->mkdir());
    }

    public function testCopy(): void
    {
        $target = $this->root . '/copied';

        $result = new Directory($this->root . '/sub')->copy($target);

        self::assertInstanceOf(Directory::class, $result);
        self::assertSame($target, $result->value);
        self::assertFileExists($target . '/b.txt');
        self::assertDirectoryExists($target . '/nested');
    }

    public function testCopyRecreatesSymlinkToDirectory(): void
    {
        $source = $this->root . '/with-link';
        \mkdir($source);
        \file_put_contents($source . '/a.txt', 'a');
        $link = $source . '/loop';
        self::assertTrue(\symlink($this->root . '/sub', $link));

        $target = $this->root . '/copied-with-link';
        new Directory($source)->copy($target);

        $copiedLink = $target . '/loop';
        self::assertTrue(\is_link($copiedLink));
        self::assertSame($this->root . '/sub', \readlink($copiedLink));
        self::assertFileExists($target . '/a.txt');
        // Leaf only: destination lists the link name, not a descended copy of `sub`.
        $destEntries = \scandir($target);
        self::assertNotFalse($destEntries);
        self::assertContains('loop', $destEntries);
        self::assertNotContains('b.txt', $destEntries);
        self::assertNotContains('nested', $destEntries);
    }

    public function testSyncRecreatesSymlinkToDirectory(): void
    {
        $source = $this->root . '/with-link';
        \mkdir($source);
        \file_put_contents($source . '/a.txt', 'a');
        $link = $source . '/to-sub';
        self::assertTrue(\symlink($this->root . '/sub', $link));

        $target = $this->root . '/synced-links';
        \mkdir($target);
        \file_put_contents($target . '/stale.txt', 'stale');
        \symlink('/tmp', $target . '/to-sub');

        new Directory($source)->sync($target, alwaysOverwrite: true, deleteMissing: true);

        self::assertFileDoesNotExist($target . '/stale.txt');
        self::assertTrue(\is_link($target . '/to-sub'));
        self::assertSame($this->root . '/sub', \readlink($target . '/to-sub'));
        self::assertFileExists($target . '/a.txt');
    }

    public function testSyncDeleteMissing(): void
    {
        $target = $this->root . '/synced';
        \mkdir($target);
        \file_put_contents($target . '/stale.txt', 'stale');

        new Directory($this->root . '/sub')->sync($target);
        self::assertFileExists($target . '/stale.txt');

        new Directory($this->root . '/sub')->sync($target, deleteMissing: true);
        self::assertFileDoesNotExist($target . '/stale.txt');
        self::assertFileExists($target . '/b.txt');
    }

    public function testMove(): void
    {
        $source = new Directory($this->root . '/sub');
        $moved  = $source->move($this->root . '/moved');

        self::assertInstanceOf(Directory::class, $moved);
        self::assertDirectoryDoesNotExist($this->root . '/sub');
        self::assertFileExists($this->root . '/moved/b.txt');
    }

    public function testRemoveRecursive(): void
    {
        $directory = new Directory($this->root . '/sub');

        self::assertTrue($directory->remove());
        self::assertDirectoryDoesNotExist($this->root . '/sub');
    }

    public function testRemoveNonRecursiveEmptyDirectory(): void
    {
        $directory = new Directory($this->root . '/sub/nested');

        self::assertTrue($directory->remove(recursive: false));
        self::assertDirectoryDoesNotExist($this->root . '/sub/nested');
    }

    public function testRemoveNonRecursiveNonEmptyThrows(): void
    {
        $this->expectException(RuntimeException::class);
        new Directory($this->root . '/sub')->remove(recursive: false);
    }

    public function testRemoveMissingDirectoryReturnsFalse(): void
    {
        self::assertFalse(new Directory($this->root . '/missing')->remove());
    }
}

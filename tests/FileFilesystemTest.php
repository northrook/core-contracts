<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Tests\Support\FilesystemStub;
use Northrook\Filesystem\File;
use Northrook\Filesystem\Path;
use Northrook\InvalidArgumentException;
use Northrook\RuntimeException;
use Northrook\Timestamp;
use PHPUnit\Framework\TestCase;

final class FileFilesystemTest extends TestCase
{
    public function testAppendReadExistsUseFilesystemWhenPresent(): void
    {
        $stub = new FilesystemStub;
        $path = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'nr-fs-' . \bin2hex(\random_bytes(4)) . '.txt';
        \file_put_contents($path, 'hello');

        try {
            $file = new File($path, filesystem: $stub);

            self::assertTrue($file->exists());
            self::assertTrue($file->append(' world'));
            self::assertSame('hello world', $file->read());

            self::assertContains('isFile', $stub->calls);
            self::assertContains('appendToFile', $stub->calls);
            self::assertContains('readFile', $stub->calls);
        } finally {
            @\unlink($path);
        }
    }

    public function testAppendWorksNativelyWithoutFilesystem(): void
    {
        $path = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'nr-fs-native-' . \bin2hex(\random_bytes(4)) . '.txt';
        \file_put_contents($path, 'a');

        try {
            $file = new File($path);

            self::assertTrue($file->append('b'));
            self::assertSame('ab', $file->read());
            self::assertTrue($file->exists());
        } finally {
            @\unlink($path);
        }
    }

    public function testPathExistsUsesFilesystemWhenPresent(): void
    {
        $stub = new FilesystemStub;
        $path = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'nr-fs-path-' . \bin2hex(\random_bytes(4)) . '.txt';
        \file_put_contents($path, '');

        try {
            $location = new Path($path, filesystem: $stub);

            self::assertTrue($location->exists());
            self::assertContains('fileExists', $stub->calls);
        } finally {
            @\unlink($path);
        }
    }

    public function testWriteUsesFilesystemWhenPresent(): void
    {
        $stub = new FilesystemStub;
        $path = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'nr-fs-write-' . \bin2hex(\random_bytes(4)) . '.txt';

        try {
            $file = new File($path, filesystem: $stub);

            self::assertTrue($file->write('payload'));
            self::assertSame('payload', $file->read());

            self::assertContains('createParentDirectory', $stub->calls);
            self::assertContains('writeFileAtomically', $stub->calls);
        } finally {
            @\unlink($path);
        }
    }

    public function testTemporaryCreatesExistingFile(): void
    {
        $file = File::temporary(
            prefix: 'nr-tmp-',
            suffix: '.tmp',
        );

        try {
            self::assertTrue($file->isFile());
            self::assertStringStartsWith('nr-tmp-', $file->basename());
            self::assertStringEndsWith('.tmp', $file->basename());
        } finally {
            $file->remove();
        }
    }

    public function testTemporaryRejectsPathSeparatorsInSuffix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename fragment');

        File::temporary(
            suffix   : '/../../escape.txt',
            directory: \sys_get_temp_dir() . '/nr-safe-' . \bin2hex(\random_bytes(4)),
        );
    }

    public function testTemporaryRejectsPathSeparatorsInPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('filename fragment');

        File::temporary(prefix: '../nr-');
    }

    public function testTemporaryRejectsEmptyPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty');

        // @phpstan-ignore-next-line
        File::temporary(prefix: '');
    }

    public function testTemporaryStaysUnderGivenDirectory(): void
    {
        $safe = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'nr-safe-' . \bin2hex(\random_bytes(4));
        \mkdir($safe, 0777, true);

        try {
            $file = File::temporary(
                prefix   : 'nr-tmp-',
                suffix   : '.tmp',
                directory: $safe,
            );

            try {
                self::assertTrue(\str_starts_with($file->value, $safe . \DIRECTORY_SEPARATOR));
                self::assertTrue($file->isFile());
            } finally {
                $file->remove();
            }
        } finally {
            @\rmdir($safe);
        }
    }

    public function testAssertConstructorThrowsForMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        new File(\sys_get_temp_dir() . '/nr-fs-missing-' . \bin2hex(\random_bytes(4)), assert: true);
    }

    public function testAssertConstructorThrowsForDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        new File(\sys_get_temp_dir(), assert: true);
    }

    public function testReadSoftFailReturnsNull(): void
    {
        $file = new File(\sys_get_temp_dir() . '/nr-fs-missing-' . \bin2hex(\random_bytes(4)));

        self::assertFalse($file->exists());
        self::assertNull($file->read(throw: false));
    }

    public function testReadThrowsByDefaultForMissingFile(): void
    {
        $this->expectException(RuntimeException::class);
        new File(\sys_get_temp_dir() . '/nr-fs-missing-' . \bin2hex(\random_bytes(4)))->read();
    }

    public function testWriteCreatesParentDirectoriesNatively(): void
    {
        $base = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'nr-fs-nested-' . \bin2hex(\random_bytes(4));
        $path = $base . \DIRECTORY_SEPARATOR . 'deep' . \DIRECTORY_SEPARATOR . 'file.txt';

        try {
            $file = new File($path);

            self::assertTrue($file->write('nested'));
            self::assertSame('nested', \file_get_contents($path));
        } finally {
            @\unlink($path);
            @\rmdir($base . \DIRECTORY_SEPARATOR . 'deep');
            @\rmdir($base);
        }
    }

    public function testWriteRejectsNonStringContentNatively(): void
    {
        $path = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'nr-fs-type-' . \bin2hex(\random_bytes(4)) . '.txt';

        try {
            $this->expectException(InvalidArgumentException::class);
            // @phpstan-ignore-next-line Testing invalid input.
            new File($path)->write(123);
        } finally {
            @\unlink($path);
        }
    }

    public function testMetadata(): void
    {
        $path = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'nr-fs-meta-' . \bin2hex(\random_bytes(4)) . '.txt';
        \file_put_contents($path, 'hello');

        try {
            $file = new File($path);

            self::assertSame(5, $file->size());
            self::assertInstanceOf(Timestamp::class, $file->modifiedAt());
            self::assertInstanceOf(Timestamp::class, $file->createdAt());
            self::assertSame('text/plain', $file->mimeType());
        } finally {
            @\unlink($path);
        }
    }

    public function testMimeTypeIsNullForMissingFile(): void
    {
        $file = new File(\sys_get_temp_dir() . '/nr-fs-missing-' . \bin2hex(\random_bytes(4)) . '.txt');

        self::assertNull($file->mimeType());
    }

    public function testMimeTypeUsesFilesystemWhenPresent(): void
    {
        $stub = new FilesystemStub;
        $path = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'nr-fs-mime-' . \bin2hex(\random_bytes(4)) . '.txt';
        \file_put_contents($path, 'hello');

        try {
            $file = new File($path, filesystem: $stub);

            self::assertSame('text/plain', $file->mimeType());
            self::assertContains('mimeType', $stub->calls);
        } finally {
            @\unlink($path);
        }
    }

    public function testCopyAndMoveNatively(): void
    {
        $base = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'nr-fs-cm-' . \bin2hex(\random_bytes(4)) . '.txt';
        \file_put_contents($base, 'data');
        $copy  = $base . '.copy';
        $moved = $base . '.moved';

        try {
            $file       = new File($base);
            $copiedFile = $file->copy($copy);

            self::assertInstanceOf(File::class, $copiedFile);
            self::assertFileExists($base);
            self::assertFileExists($copy);
            self::assertSame('data', \file_get_contents($copy));

            $movedFile = $copiedFile->move($moved);

            self::assertInstanceOf(File::class, $movedFile);
            self::assertFileDoesNotExist($copy);
            self::assertFileExists($moved);
            self::assertSame($moved, $movedFile->value);
        } finally {
            @\unlink($base);
            @\unlink($copy);
            @\unlink($moved);
        }
    }

    public function testPathView(): void
    {
        $file = new File('/x/y.txt');
        $path = $file->path();

        self::assertInstanceOf(Path::class, $path);
        self::assertNotInstanceOf(File::class, $path);
        self::assertSame('/x/y.txt', $path->value);
    }
}

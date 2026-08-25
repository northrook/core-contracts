<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Filesystem\FilesystemException;
use Northrook\FilesystemInterface;

/**
 * Minimal {@see FilesystemInterface} that records calls and delegates to native PHP.
 */
final class FilesystemStub implements FilesystemInterface
{
    /** @var list<non-empty-string> */
    public array $calls = [];

    /** When set, {@see copyFile()} throws this instead of copying. */
    public null|\Throwable $copyFileError = null;

    /** When set, {@see remove()} throws this instead of removing. */
    public null|\Throwable $removeError = null;

    public function copyFile(
        string $source,
        string $target,
        bool   $alwaysOverwrite = false,
    ): void {
        $this->calls[] = 'copyFile';

        if ($this->copyFileError !== null) {
            throw $this->copyFileError;
        }

        if (! \copy($source, $target)) {
            throw new FilesystemException(
                message: 'copyFile failed',
                path   : $target,
            );
        }
    }

    public function fileExists(
        string $path,
    ): bool {
        $this->calls[] = 'fileExists';

        return \file_exists($path);
    }

    public function createDirectory(
        string|iterable $paths,
        int             $mode = 0777,
    ): void {
        $this->calls[] = 'createDirectory';

        foreach ((array) $paths as $path) {
            if (! \is_dir($path) && ! @\mkdir($path, $mode, true) && ! \is_dir($path)) {
                throw new FilesystemException(
                    message: 'createDirectory failed',
                    path   : $path,
                );
            }
        }
    }

    public function createParentDirectory(
        string|iterable $paths,
        int             $mode = 0777,
    ): void {
        $this->calls[] = 'createParentDirectory';

        foreach ((array) $paths as $path) {
            $parent = \dirname($path);

            if ($parent !== '' && $parent !== '.' && ! \is_dir($parent)) {
                $this->createDirectory($parent, $mode);
            }
        }
    }

    public function touch(
        string|iterable $paths,
        null|int        $modifiedTime = null,
        null|int        $accessTime = null,
    ): void {
        $this->calls[] = 'touch';
        throw new FilesystemException(message: 'not implemented');
    }

    public function remove(
        string|iterable $paths,
    ): void {
        $this->calls[] = 'remove';

        if ($this->removeError !== null) {
            throw $this->removeError;
        }

        foreach ((array) $paths as $path) {
            if (\is_file($path) || \is_link($path)) {
                @\unlink($path);
            }
            elseif (\is_dir($path)) {
                @\rmdir($path);
            }
        }
    }

    public function setPermissions(
        string|iterable $paths,
        int             $mode,
        int             $umask = 0000,
        bool            $recursive = false,
    ): void {
        $this->calls[] = 'setPermissions';
        throw new FilesystemException(message: 'not implemented');
    }

    public function setOwner(
        string|iterable $paths,
        string|int      $owner,
        bool            $recursive = false,
    ): void {
        $this->calls[] = 'setOwner';
        throw new FilesystemException(message: 'not implemented');
    }

    public function setGroup(
        string|iterable $paths,
        string|int      $group,
        bool            $recursive = false,
    ): void {
        $this->calls[] = 'setGroup';
        throw new FilesystemException(message: 'not implemented');
    }

    public function move(
        string $source,
        string $target,
        bool   $overwrite = false,
    ): void {
        $this->calls[] = 'move';
        throw new FilesystemException(message: 'not implemented');
    }

    public function isReadable(
        string $path,
    ): bool {
        $this->calls[] = 'isReadable';

        return \is_readable($path);
    }

    public function isWritable(
        string $path,
    ): bool {
        $this->calls[] = 'isWritable';

        return \is_writable($path);
    }

    public function isFile(
        string $path,
    ): bool {
        $this->calls[] = 'isFile';

        return \is_file($path);
    }

    public function isDirectory(
        string $path,
    ): bool {
        $this->calls[] = 'isDirectory';

        return \is_dir($path);
    }

    public function isLink(
        string $path,
    ): bool {
        $this->calls[] = 'isLink';

        return \is_link($path);
    }

    public function isAbsolutePath(
        string $path,
    ): bool {
        $this->calls[] = 'isAbsolutePath';

        return \str_starts_with($path, '/') || \strlen($path) > 2 && $path[1] === ':';
    }

    public function createSymlink(
        string $source,
        string $target,
        bool   $copyDirectoryOnWindows = false,
    ): void {
        $this->calls[] = 'createSymlink';
        throw new FilesystemException(message: 'not implemented');
    }

    public function createHardlink(
        string          $source,
        string|iterable $targets,
    ): void {
        $this->calls[] = 'createHardlink';
        throw new FilesystemException(message: 'not implemented');
    }

    public function readLinkTarget(
        string $path,
    ): null|string {
        $this->calls[] = 'readLinkTarget';

        return null;
    }

    public function resolvePath(
        string $path,
    ): null|string {
        $this->calls[] = 'resolvePath';
        $resolved      = \realpath($path);

        return $resolved === false ? null : $resolved;
    }

    public function makeRelativePath(
        string $path,
        string $fromDirectory,
    ): string {
        $this->calls[] = 'makeRelativePath';
        throw new FilesystemException(message: 'not implemented');
    }

    public function syncDirectory(
        string            $sourceDirectory,
        string            $destinationDirectory,
        null|\Traversable $entries = null,
        bool              $alwaysOverwrite = false,
        bool              $deleteMissingFiles = false,
        bool              $copyLinksOnWindows = false,
    ): void {
        $this->calls[] = 'syncDirectory';
        throw new FilesystemException(message: 'not implemented');
    }

    public function listDirectory(
        string $directory,
    ): array {
        $this->calls[] = 'listDirectory';

        $entries = @\scandir($directory);

        if ($entries === false) {
            throw new FilesystemException(
                message: 'listDirectory failed',
                path   : $directory,
            );
        }

        $paths = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $paths[] = $directory . \DIRECTORY_SEPARATOR . $entry;
        }

        return $paths;
    }

    public function createTemporaryFile(
        string $directory,
        string $prefix,
        string $suffix = '',
    ): string {
        $this->calls[] = 'createTemporaryFile';
        $path          = $directory . \DIRECTORY_SEPARATOR . $prefix . \bin2hex(\random_bytes(4)) . $suffix;
        \file_put_contents($path, '');

        return $path;
    }

    public function writeFileAtomically(
        string $path,
        mixed  $content,
    ): void {
        $this->calls[] = 'writeFileAtomically';

        if (\file_put_contents($path, $content) === false) {
            throw new FilesystemException(
                message: 'writeFileAtomically failed',
                path   : $path,
            );
        }
    }

    public function appendToFile(
        string $path,
        mixed  $content,
        bool   $lock = false,
    ): void {
        $this->calls[] = 'appendToFile';
        $flags         = \FILE_APPEND;

        if ($lock) {
            $flags |= \LOCK_EX;
        }

        if (\file_put_contents($path, $content, $flags) === false) {
            throw new FilesystemException(
                message: 'appendToFile failed',
                path   : $path,
            );
        }
    }

    public function readFile(
        string $path,
    ): string {
        $this->calls[] = 'readFile';
        $contents      = \file_get_contents($path);

        if ($contents === false) {
            throw new FilesystemException(
                message: 'readFile failed',
                path   : $path,
            );
        }

        return $contents;
    }

    public function fileSize(
        string $path,
    ): int {
        $this->calls[] = 'fileSize';
        $size          = \filesize($path);

        if ($size === false) {
            throw new FilesystemException(
                message: 'fileSize failed',
                path   : $path,
            );
        }

        return $size;
    }

    public function modifiedTime(
        string $path,
    ): int {
        $this->calls[] = 'modifiedTime';
        $mtime         = \filemtime($path);

        if ($mtime === false) {
            throw new FilesystemException(
                message: 'modifiedTime failed',
                path   : $path,
            );
        }

        return $mtime;
    }

    public function createdTime(
        string $path,
    ): int {
        $this->calls[] = 'createdTime';
        $ctime         = \filectime($path);

        if ($ctime === false) {
            throw new FilesystemException(
                message: 'createdTime failed',
                path   : $path,
            );
        }

        return $ctime;
    }

    public function mimeType(
        string $path,
    ): null|string {
        $this->calls[] = 'mimeType';

        if (! \class_exists(\finfo::class) || ! \is_file($path)) {
            return null;
        }

        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path);

        return \is_string($mime) && $mime !== '' ? $mime : null;
    }

    public function glob(
        string|iterable $patterns,
        null|int        $flags = null,
    ): array {
        $this->calls[] = 'glob';
        $result        = [];

        foreach ((array) $patterns as $pattern) {
            $matched = $flags === null ? \glob($pattern) : \glob($pattern, $flags);

            if ($matched === false) {
                continue;
            }

            foreach ($matched as $match) {
                $result[] = $match;
            }
        }

        return \array_values(\array_unique($result));
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Filesystem;

use Northrook\FilesystemInterface;
use Northrook\Normalize;
use Northrook\Runtime\Assert;

/**
 * Lightweight {@see FilesystemInterface} backed by native PHP.
 *
 * Intended for ad-hoc use outside a Kernel/container. Prefer the hardened
 * {@see Filesystem} from `northrook/php-filesystem` under application runtime.
 *
 * Atomic write, remove, move, and sync are best-effort — not behavioral parity
 * with the hardened implementation (no ErrorHandler, relocate locks, etc.).
 */
final class NativeFilesystem implements FilesystemInterface
{
    public function copyFile(
        string $source,
        string $target,
        bool   $alwaysOverwrite = false,
    ): void {
        $this->assertPathLength($source);
        $this->assertPathLength($target);

        if (! $this->fileExists($source)) {
            throw new FileNotFoundException(path: $source);
        }

        if (! $alwaysOverwrite && \is_file($target)) {
            $sourceMtime = @\filemtime($source);
            $targetMtime = @\filemtime($target);

            if ($sourceMtime !== false && $targetMtime !== false && $targetMtime >= $sourceMtime) {
                return;
            }
        }

        $parent = \dirname($target);

        if ($parent !== '' && $parent !== '.' && ! \is_dir($parent)) {
            $this->createDirectory($parent);
        }

        if (! @\copy($source, $target)) {
            throw new FilesystemException(
                message: "Unable to copy '{$source}' to '{$target}'.",
                path   : $target,
                context: ['source' => $source, 'target' => $target],
            );
        }
    }

    public function fileExists(
        string $path,
    ): bool {
        $this->assertPathLength($path);

        return \file_exists($path);
    }

    public function createDirectory(
        string|iterable $paths,
        int             $mode = 0777,
    ): void {
        foreach ($this->paths($paths) as $path) {
            $this->assertPathLength($path);

            if (\is_dir($path)) {
                continue;
            }

            if (! @\mkdir($path, $mode, true) && ! \is_dir($path)) {
                throw new FilesystemException(
                    message: "Unable to create directory '{$path}'.",
                    path   : $path,
                );
            }
        }
    }

    public function createParentDirectory(
        string|iterable $paths,
        int             $mode = 0777,
    ): void {
        foreach ($this->paths($paths) as $path) {
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
        foreach ($this->paths($paths) as $path) {
            $this->assertPathLength($path);

            $ok =
                $modifiedTime === null && $accessTime === null
                    ? @\touch($path)
                    : @\touch(
                        $path,
                        $modifiedTime ?? \time(),
                        $accessTime ?? $modifiedTime ?? \time(),
                    );

            if (! $ok) {
                throw new FilesystemException(
                    message: "Unable to touch '{$path}'.",
                    path   : $path,
                );
            }
        }
    }

    public function remove(
        string|iterable $paths,
    ): void {
        foreach ($this->paths($paths) as $path) {
            $this->assertPathLength($path);

            if (! $this->removePath($path)) {
                throw new FilesystemException(
                    message: "Unable to remove '{$path}'.",
                    path   : $path,
                );
            }
        }
    }

    public function setPermissions(
        string|iterable $paths,
        int             $mode,
        int             $umask = 0000,
        bool            $recursive = false,
    ): void {
        $permissions = $mode & ~$umask;

        foreach ($this->paths($paths) as $path) {
            $this->assertPathLength($path);

            if ($recursive && \is_dir($path) && ! \is_link($path)) {
                foreach ($this->walkChildren($path) as $child) {
                    if (! @\chmod($child, $permissions)) {
                        throw new FilesystemException(
                            message: "Unable to set permissions on '{$child}'.",
                            path   : $child,
                        );
                    }
                }
            }

            if (! @\chmod($path, $permissions)) {
                throw new FilesystemException(
                    message: "Unable to set permissions on '{$path}'.",
                    path   : $path,
                );
            }
        }
    }

    public function setOwner(
        string|iterable $paths,
        string|int      $owner,
        bool            $recursive = false,
    ): void {
        foreach ($this->paths($paths) as $path) {
            $this->assertPathLength($path);

            if ($recursive && \is_dir($path) && ! \is_link($path)) {
                foreach ($this->walkChildren($path) as $child) {
                    if (! @\chown($child, $owner)) {
                        throw new FilesystemException(
                            message: "Unable to set owner on '{$child}'.",
                            path   : $child,
                        );
                    }
                }
            }

            if (! @\chown($path, $owner)) {
                throw new FilesystemException(
                    message: "Unable to set owner on '{$path}'.",
                    path   : $path,
                );
            }
        }
    }

    public function setGroup(
        string|iterable $paths,
        string|int      $group,
        bool            $recursive = false,
    ): void {
        foreach ($this->paths($paths) as $path) {
            $this->assertPathLength($path);

            if ($recursive && \is_dir($path) && ! \is_link($path)) {
                foreach ($this->walkChildren($path) as $child) {
                    if (! @\chgrp($child, $group)) {
                        throw new FilesystemException(
                            message: "Unable to set group on '{$child}'.",
                            path   : $child,
                        );
                    }
                }
            }

            if (! @\chgrp($path, $group)) {
                throw new FilesystemException(
                    message: "Unable to set group on '{$path}'.",
                    path   : $path,
                );
            }
        }
    }

    public function move(
        string $source,
        string $target,
        bool   $overwrite = false,
    ): void {
        $this->assertPathLength($source);
        $this->assertPathLength($target);

        if (! $this->fileExists($source) && ! \is_link($source)) {
            throw new FileNotFoundException(path: $source);
        }

        if (\file_exists($target) || \is_link($target)) {
            if (! $overwrite) {
                throw new FilesystemException(
                    message: "Unable to move '{$source}' to '{$target}': target exists.",
                    path   : $target,
                    context: ['source' => $source, 'target' => $target],
                );
            }

            if (! $this->removePath($target)) {
                throw new FilesystemException(
                    message: "Unable to replace target '{$target}'.",
                    path   : $target,
                );
            }
        }

        $parent = \dirname($target);

        if ($parent !== '' && $parent !== '.' && ! \is_dir($parent)) {
            $this->createDirectory($parent);
        }

        if (@\rename($source, $target)) {
            return;
        }

        if (\is_dir($source) && ! \is_link($source)) {
            $this->syncDirectory($source, $target, alwaysOverwrite: true);
            $this->remove($source);

            return;
        }

        if (! @\copy($source, $target)) {
            throw new FilesystemException(
                message: "Unable to move '{$source}' to '{$target}'.",
                path   : $target,
                context: ['source' => $source, 'target' => $target],
            );
        }

        if (! $this->removePath($source)) {
            throw new FilesystemException(
                message: "Moved to '{$target}' but failed to remove source '{$source}'.",
                path   : $source,
                context: ['source' => $source, 'target' => $target],
            );
        }
    }

    public function isReadable(
        string $path,
    ): bool {
        $this->assertPathLength($path);

        return \is_readable($path);
    }

    public function isWritable(
        string $path,
    ): bool {
        $this->assertPathLength($path);

        return \is_writable($path);
    }

    public function isFile(
        string $path,
    ): bool {
        $this->assertPathLength($path);

        return \is_file($path);
    }

    public function isDirectory(
        string $path,
    ): bool {
        $this->assertPathLength($path);

        return \is_dir($path);
    }

    public function isLink(
        string $path,
    ): bool {
        $this->assertPathLength($path);

        return \is_link($path);
    }

    public function isAbsolutePath(
        string $path,
    ): bool {
        if ($path === '') {
            return false;
        }

        $this->assertPathLength($path);

        return Normalize::isAbsolutePath($path);
    }

    public function createSymlink(
        string $source,
        string $target,
        bool   $copyDirectoryOnWindows = false,
    ): void {
        $this->assertPathLength($source);
        $this->assertPathLength($target);

        if ($copyDirectoryOnWindows && \DIRECTORY_SEPARATOR === '\\' && \is_dir($source)) {
            $this->syncDirectory($source, $target, alwaysOverwrite: true);

            return;
        }

        $parent = \dirname($target);

        if ($parent !== '' && $parent !== '.' && ! \is_dir($parent)) {
            $this->createDirectory($parent);
        }

        if (\file_exists($target) || \is_link($target)) {
            if (! $this->removePath($target)) {
                throw new FilesystemException(
                    message: "Unable to replace '{$target}' with a symlink.",
                    path   : $target,
                );
            }
        }

        if (! @\symlink($source, $target)) {
            throw new FilesystemException(
                message: "Unable to create symlink '{$target}' -> '{$source}'.",
                path   : $target,
                context: ['source' => $source, 'target' => $target],
            );
        }
    }

    public function createHardlink(
        string          $source,
        string|iterable $targets,
    ): void {
        $this->assertPathLength($source);

        if (! $this->fileExists($source)) {
            throw new FileNotFoundException(path: $source);
        }

        foreach ($this->paths($targets) as $target) {
            $this->assertPathLength($target);

            $parent = \dirname($target);

            if ($parent !== '' && $parent !== '.' && ! \is_dir($parent)) {
                $this->createDirectory($parent);
            }

            if (\file_exists($target) || \is_link($target)) {
                if (! $this->removePath($target)) {
                    throw new FilesystemException(
                        message: "Unable to replace '{$target}' with a hard link.",
                        path   : $target,
                    );
                }
            }

            if (! @\link($source, $target)) {
                throw new FilesystemException(
                    message: "Unable to create hard link '{$target}' -> '{$source}'.",
                    path   : $target,
                    context: ['source' => $source, 'target' => $target],
                );
            }
        }
    }

    public function readLinkTarget(
        string $path,
    ): null|string {
        $this->assertPathLength($path);

        if (! \is_link($path)) {
            return null;
        }

        $target = @\readlink($path);

        return $target === false ? null : $target;
    }

    public function resolvePath(
        string $path,
    ): null|string {
        if (! $this->fileExists($path)) {
            return null;
        }

        $resolved = \realpath($path);

        return $resolved === false ? null : $resolved;
    }

    public function makeRelativePath(
        string $path,
        string $fromDirectory,
    ): string {
        if (! $this->isAbsolutePath($fromDirectory)) {
            throw new FilesystemException(
                message: "The start path `{$fromDirectory}` is not absolute.",
                path   : $fromDirectory,
            );
        }

        if (! $this->isAbsolutePath($path)) {
            throw new FilesystemException(
                message: "The end path `{$path}` is not absolute.",
                path   : $path,
            );
        }

        $originalEndPath = $path;

        if (\DIRECTORY_SEPARATOR === '\\') {
            $path          = Normalize::slashes($path);
            $fromDirectory = Normalize::slashes($fromDirectory);
        }

        $splitDriveLetter = static fn(string $segment): array => (
            \strlen($segment) > 2 && $segment[1] === ':' && $segment[2] === '/' && \str_contains(\CHARSET_ALPHA, $segment[0])
                ? [\substr($segment, 2), \strtoupper($segment[0])]
                : [$segment, null]
        );

        $splitPath = static function(
            string $segment,
        ): array {
            $result = [];

            foreach (\explode('/', \trim($segment, '/')) as $part) {
                if ($part === '..') {
                    \array_pop($result);
                }
                elseif ($part !== '.' && $part !== '') {
                    $result[] = $part;
                }
            }

            return $result;
        };

        [$path, $endDriveLetter] = $splitDriveLetter($path);
        [$fromDirectory, $startDriveLetter] = $splitDriveLetter($fromDirectory);

        $startPathArr = $splitPath($fromDirectory);
        $endPathArr   = $splitPath($path);

        if ($endDriveLetter && $startDriveLetter && $endDriveLetter !== $startDriveLetter) {
            return $endDriveLetter . ':/' . ( $endPathArr !== [] ? \implode('/', $endPathArr) . '/' : '' );
        }

        $index = 0;

        while (isset($startPathArr[$index], $endPathArr[$index]) && $startPathArr[$index] === $endPathArr[$index]) {
            ++$index;
        }

        $depth            = \count($startPathArr) - $index;
        $traverser        = \str_repeat('../', $depth);
        $endPathRemainder = \implode('/', \array_slice($endPathArr, $index));
        $relativePath     = $traverser . ( $endPathRemainder !== '' ? $endPathRemainder . '/' : '' );

        if (\str_ends_with($relativePath, '/') && $this->isFile($originalEndPath)) {
            $relativePath = \substr($relativePath, 0, -1);
        }

        return $relativePath === '' ? './' : $relativePath;
    }

    public function syncDirectory(
        string            $sourceDirectory,
        string            $destinationDirectory,
        null|\Traversable $entries = null,
        bool              $alwaysOverwrite = false,
        bool              $deleteMissingFiles = false,
        bool              $copyLinksOnWindows = false,
    ): void {
        unset($entries, $copyLinksOnWindows);

        $this->assertPathLength($sourceDirectory);
        $this->assertPathLength($destinationDirectory);

        if (! \is_dir($sourceDirectory)) {
            throw new FilesystemException(
                message: "Unable to sync '{$sourceDirectory}': not a directory.",
                path   : $sourceDirectory,
            );
        }

        if (! \is_dir($destinationDirectory)) {
            $this->createDirectory($destinationDirectory);
        }

        $scanned = @\scandir($sourceDirectory);

        if ($scanned === false) {
            throw new FilesystemException(
                message: "Unable to read directory '{$sourceDirectory}'.",
                path   : $sourceDirectory,
            );
        }

        $seen = [];

        foreach ($scanned as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $seen[$entry] = true;
            $from         = $sourceDirectory . \DIR_SEP . $entry;
            $to           = $destinationDirectory . \DIR_SEP . $entry;

            if (\is_link($from)) {
                $linkTarget = \readlink($from);

                if ($linkTarget === false) {
                    throw new FilesystemException(
                        message: "Unable to read symlink '{$from}'.",
                        path   : $from,
                    );
                }

                if (! $alwaysOverwrite && \is_link($to) && \readlink($to) === $linkTarget) {
                    continue;
                }

                if (\file_exists($to) || \is_link($to)) {
                    if (! $this->removePath($to)) {
                        throw new FilesystemException(
                            message: "Unable to replace '{$to}' with a symlink.",
                            path   : $to,
                        );
                    }
                }

                $this->createParentDirectory($to);

                if (! @\symlink($linkTarget, $to)) {
                    throw new FilesystemException(
                        message: "Unable to create symlink '{$to}' -> '{$linkTarget}'.",
                        path   : $to,
                        context: [
                            'source' => $from,
                            'target' => $to,
                            'link'   => $linkTarget,
                        ],
                    );
                }

                continue;
            }

            if (\is_dir($from)) {
                $this->syncDirectory(
                    sourceDirectory     : $from,
                    destinationDirectory: $to,
                    alwaysOverwrite     : $alwaysOverwrite,
                    deleteMissingFiles  : $deleteMissingFiles,
                );

                continue;
            }

            if (! $alwaysOverwrite && \is_file($to)) {
                $toMtime   = @\filemtime($to);
                $fromMtime = @\filemtime($from);

                if ($toMtime !== false && $fromMtime !== false && $toMtime >= $fromMtime) {
                    continue;
                }
            }

            if (\file_exists($to) || \is_link($to)) {
                if (! $this->removePath($to)) {
                    throw new FilesystemException(
                        message: "Unable to replace '{$to}' with a file.",
                        path   : $to,
                    );
                }
            }

            $this->createParentDirectory($to);

            if (! @\copy($from, $to)) {
                throw new FilesystemException(
                    message: "Unable to copy '{$from}' to '{$to}'.",
                    path   : $to,
                    context: ['source' => $from, 'target' => $to],
                );
            }
        }

        if (! $deleteMissingFiles) {
            return;
        }

        $destEntries = @\scandir($destinationDirectory);

        if ($destEntries === false) {
            throw new FilesystemException(
                message: "Unable to read directory '{$destinationDirectory}'.",
                path   : $destinationDirectory,
            );
        }

        foreach ($destEntries as $entry) {
            if ($entry === '.' || $entry === '..' || isset($seen[$entry])) {
                continue;
            }

            $extra = $destinationDirectory . \DIR_SEP . $entry;

            if (! $this->removePath($extra)) {
                throw new FilesystemException(
                    message: "Unable to remove missing sync target '{$extra}'.",
                    path   : $extra,
                );
            }
        }
    }

    public function listDirectory(
        string $directory,
    ): array {
        $this->assertPathLength($directory);

        if (! \is_dir($directory)) {
            throw new FilesystemException(
                message: "Failed to list directory `{$directory}`: Not a directory.",
                path   : $directory,
            );
        }

        $entries = @\scandir($directory);

        if ($entries === false) {
            throw new FilesystemException(
                message: "Unable to list directory '{$directory}'.",
                path   : $directory,
            );
        }

        $paths = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $paths[] = $directory . \DIR_SEP . $entry;
        }

        return $paths;
    }

    public function createTemporaryFile(
        string $directory,
        string $prefix,
        string $suffix = '',
    ): string {
        $this->assertPathLength($directory);

        if (! \is_dir($directory)) {
            $this->createDirectory($directory);
        }

        $temp = @\tempnam($directory, $prefix);

        if ($temp === false) {
            throw new FilesystemException(
                message: "Unable to create temporary file in '{$directory}'.",
                path   : $directory,
            );
        }

        if ($suffix === '') {
            return $temp;
        }

        $withSuffix = $temp . $suffix;

        if (! @\rename($temp, $withSuffix)) {
            @\unlink($temp);

            throw new FilesystemException(
                message: "Unable to apply suffix to temporary file '{$temp}'.",
                path   : $withSuffix,
            );
        }

        return $withSuffix;
    }

    public function writeFileAtomically(
        string $path,
        mixed  $content,
    ): void {
        $this->assertPathLength($path);
        $this->createParentDirectory($path);

        $directory = \dirname($path);
        $temp      = @\tempnam($directory, 'nr_');

        if ($temp === false) {
            throw new FilesystemException(
                message: "Unable to create temp file for atomic write of '{$path}'.",
                path   : $path,
            );
        }

        $mode = \is_file($path) ? @\fileperms($path) : false;

        try {
            if (\file_put_contents($temp, $content) === false) {
                throw new FilesystemException(
                    message: "Unable to write temporary content for '{$path}'.",
                    path   : $path,
                );
            }

            if ($mode !== false) {
                @\chmod($temp, $mode & 0777);
            }

            if (! @\rename($temp, $path)) {
                if (! @\copy($temp, $path) || ! @\unlink($temp)) {
                    throw new FilesystemException(
                        message: "Unable to promote temporary file to '{$path}'.",
                        path   : $path,
                    );
                }
            }
            $temp = null;
        }
        finally {
            if ($temp !== null && \is_file($temp)) {
                @\unlink($temp);
            }
        }
    }

    public function appendToFile(
        string $path,
        mixed  $content,
        bool   $lock = false,
    ): void {
        $this->assertPathLength($path);
        $this->createParentDirectory($path);

        $flags = \FILE_APPEND;

        if ($lock) {
            $flags |= \LOCK_EX;
        }

        if (\file_put_contents($path, $content, $flags) === false) {
            throw new FilesystemException(
                message: "Unable to append to '{$path}'.",
                path   : $path,
            );
        }
    }

    public function readFile(
        string $path,
    ): string {
        $this->assertPathLength($path);

        if (\is_dir($path)) {
            throw new FilesystemException(
                message: "Failed to read file `{$path}`: File is a directory.",
                path   : $path,
            );
        }

        $contents = @\file_get_contents($path);

        if ($contents === false) {
            throw new FilesystemException(
                message: "Unable to read file '{$path}'.",
                path   : $path,
            );
        }

        return $contents;
    }

    public function fileSize(
        string $path,
    ): int {
        $this->assertPathLength($path);

        $size = @\filesize($path);

        if ($size === false) {
            throw new FilesystemException(
                message: "Unable to read file size of '{$path}'.",
                path   : $path,
            );
        }

        return $size;
    }

    public function modifiedTime(
        string $path,
    ): int {
        $this->assertPathLength($path);

        $mtime = @\filemtime($path);

        if ($mtime === false) {
            throw new FilesystemException(
                message: "Unable to read modified time of '{$path}'.",
                path   : $path,
            );
        }

        return $mtime;
    }

    public function createdTime(
        string $path,
    ): int {
        $this->assertPathLength($path);

        $ctime = @\filectime($path);

        if ($ctime === false) {
            throw new FilesystemException(
                message: "Unable to read created time of '{$path}'.",
                path   : $path,
            );
        }

        return $ctime;
    }

    public function mimeType(
        string $path,
    ): null|string {
        $this->assertPathLength($path);

        if (! \is_file($path)) {
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
        $result = [];
        $flags  ??= \GLOB_NOSORT;

        if (\defined('GLOB_BRACE')) {
            $flags |= \GLOB_BRACE;
        }

        foreach ($this->paths($patterns) as $pattern) {
            $this->assertPathLength($pattern);

            $matched = @\glob($pattern, $flags);

            if ($matched === false) {
                continue;
            }

            foreach ($matched as $match) {
                $result[] = $match;
            }
        }

        return \array_values(\array_unique($result));
    }

    /**
     * @throws FilesystemException
     */
    private function assertPathLength(
        string $path,
    ): void {
        /** @var bool|\Throwable $failure */
        $failure = true;

        if (Assert::validPathLength($path, catch: $failure)) {
            return;
        }

        throw new FilesystemException(
            message : $failure instanceof \Throwable
                ? $failure->getMessage()
                : "Invalid path length for `{$path}`.",
            path    : $path,
            previous: $failure instanceof \Throwable ? $failure : null,
        );
    }

    /**
     * @param string|iterable<string> $paths
     *
     * @return list<string>
     */
    private function paths(
        string|iterable $paths,
    ): array {
        if (\is_string($paths)) {
            return [$paths];
        }

        $list = [];

        foreach ($paths as $path) {
            $list[] = $path;
        }

        return $list;
    }

    private function removePath(
        string $path,
    ): bool {
        if (\is_link($path) || \is_file($path)) {
            return @\unlink($path);
        }

        if (\is_dir($path)) {
            return $this->removeTree($path);
        }

        return false;
    }

    private function removeTree(
        string $directory,
    ): bool {
        $entries = @\scandir($directory);

        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $directory . \DIR_SEP . $entry;

            if (\is_dir($child) && ! \is_link($child)) {
                if (! $this->removeTree($child)) {
                    return false;
                }
            }
            elseif (! @\unlink($child)) {
                return false;
            }
        }

        return @\rmdir($directory);
    }

    /**
     * @return list<string>
     */
    private function walkChildren(
        string $directory,
    ): array {
        $paths = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME,
            ),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $pathname) {
            if (\is_string($pathname)) {
                $paths[] = $pathname;
            }
        }

        return $paths;
    }
}

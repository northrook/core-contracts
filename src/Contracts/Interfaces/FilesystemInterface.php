<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Northrook\Contracts\Exceptions\FileNotFoundException;
use Northrook\Contracts\Exceptions\FilesystemException;
use Traversable;

interface FilesystemInterface
{
    /**
     * Copies a file from `source` to `target`.
     *
     * If the `target` is older than the `source`, it is overwritten.
     *
     * To overwrite when `target` is newer, set `alwaysOverwrite` to `true`.
     *
     * @throws FileNotFoundException
     * @throws FilesystemException
     */
    public function copyFile(
        string $source,
        string $target,
        bool $alwaysOverwrite = false,
    ): void;

    /**
     * Returns true if the file or directory exists.
     *
     * @param string $paths
     */
    public function fileExists(
        string $path,
    ): bool;

    /**
     * Creates a directory.
     *
     * Recursively creates parent directories as needed.
     *
     * @param string|iterable<string> $paths
     * @param int     $mode
     *
     * @throws FilesystemException
     */
    public function createDirectory(
        string|array $paths,
        int $mode = 0777,
    ): void;

    /**
     * Creates the parent directory for a path if it does not exist.
     *
     * @param string|iterable<string> $paths
     * @param int     $mode
     *
     * @throws FilesystemException
     */
    public function createParentDirectory(
        string|iterable $paths,
        int $mode = 0777,
    ): void;

    /**
     * Updates access and modification time.
     *
     * @param string|iterable<string> $paths
     *
     * @throws FilesystemException
     */
    public function touch(
        string|iterable $paths,
        null|int $modifiedTime = null,
        null|int $accessTime = null,
    ): void;

    /**
     * Removes files, links, or directories.
     *
     * @param string|iterable<string> $paths
     *
     * @throws FilesystemException
     */
    public function remove(
        string|iterable $paths,
    ): void;

    /**
     * Changes file or directory permissions.
     *
     * @param string|iterable<string> $paths
     *
     * @throws FilesystemException
     */
    public function setPermissions(
        string|iterable $paths,
        int $mode,
        int $umask = 0000,
        bool $recursive = false,
    ): void;

    /**
     * Changes file or directory owner.
     *
     * @param string|iterable<string> $paths
     *
     * @throws FilesystemException
     */
    public function setOwner(
        string|iterable $paths,
        string|int $owner,
        bool $recursive = false,
    ): void;

    /**
     * Changes file or directory group.
     *
     * @param string|iterable<string> $paths
     *
     * @throws FilesystemException
     */
    public function setGroup(
        string|iterable $paths,
        string|int $group,
        bool $recursive = false,
    ): void;

    /**
     * Moves or renames a file or directory.
     *
     * @throws FilesystemException
     */
    public function move(
        string $source,
        string $target,
        bool $overwrite = false,
    ): void;

    public function isReadable(
        string $path,
    ): bool;

    public function isWritable(
        string $path,
    ): bool;

    public function isFile(
        string $path,
    ): bool;

    public function isDirectory(
        string $path,
    ): bool;

    public function isLink(
        string $path,
    ): bool;

    public function isAbsolutePath(
        string $path,
    ): bool;

    /**
     * Creates a symbolic link.
     *
     * @throws FilesystemException
     */
    public function createSymlink(
        string $source,
        string $target,
        bool $copyDirectoryOnWindows = false,
    ): void;

    /**
     * Creates one or more hard links.
     *
     * @param string|iterable<string>  $targets
     *
     * @throws FileNotFoundException
     * @throws FilesystemException
     */
    public function createHardlink(
        string $source,
        string|iterable $targets,
    ): void;

    /**
     * Returns the direct target of a symbolic link.
     *
     * Returns null when the path does not exist or is not a link.
     */
    public function readLinkTarget(
        string $path,
    ): null|string;

    /**
     * Returns the fully resolved absolute path.
     *
     * Returns null when the path does not exist.
     */
    public function resolvePath(
        string $path,
    ): null|string;

    /**
     * Converts an existing path to one relative to another path.
     */
    public function makeRelativePath(
        string $path,
        string $fromDirectory,
    ): string;

    /**
     * Synchronizes a source directory into a destination directory.
     *
     * @param Traversable<int, \SplFileInfo|string>|null $entries
     *
     * @throws FilesystemException
     */
    public function syncDirectory(
        string $sourceDirectory,
        string $destinationDirectory,
        null|Traversable $entries = null,
        bool $alwaysOverwrite = false,
        bool $deleteMissingFiles = false,
        bool $copyLinksOnWindows = false,
    ): void;

    /**
     * Creates a temporary file and returns its full path.
     *
     * @throws FilesystemException
     */
    public function createTemporaryFile(
        string $directory,
        string $prefix,
        string $suffix = '',
    ): string;

    /**
     * Atomically writes content to a file.
     *
     * @param string|resource $content
     *
     * @throws FilesystemException
     */
    public function writeFileAtomically(
        string $path,
        mixed $content,
    ): void;

    /**
     * Appends content to a file.
     *
     * @param string|resource $content
     *
     * @throws FilesystemException
     */
    public function appendToFile(
        string $path,
        mixed $content,
        bool $lock = false,
    ): void;

    /**
     * Reads a file as a string.
     *
     * @throws FilesystemException
     */
    public function readFile(
        string $path,
    ): string;

    /**
     * Returns the file size in bytes.
     *
     * @throws FilesystemException
     */
    public function fileSize(
        string $path,
    ): int;

    /**
     * Returns the file modification time as a Unix timestamp.
     *
     * @throws FilesystemException
     */
    public function modifiedTime(
        string $path,
    ): int;

    /**
     * Returns the file creation time as a Unix timestamp.
     *
     * @param string $path
     * @return int
     */
    public function createdTime(
        string $path,
    ): int;
}

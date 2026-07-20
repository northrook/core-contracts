<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use RuntimeException;
use SplFileInfo;
use Stringable;
use ValueError;

/**
 * Mutable filesystem path value object.
 *
 * Represents a local path string and exposes filesystem introspection and I/O.
 *
 * Implementing classes are expected to delegate disk operations to a {@see FilesystemInterface} collaborator.
 *
 * Mutating methods update the instance in place and return {@see static} for chaining.
 */
interface PathInterface extends Stringable
{
    /**
     * Normalized filesystem path string.
     *
     * @var non-empty-string
     */
    public string $value { get; }

    /**
     * Appends a path segment to this path.
     *
     * @throws ValueError When this path resolves to an existing file.
     */
    public function append(
        string|Stringable $string,
    ): static;

    /**
     * Whether this path exists on disk.
     *
     * @throws RuntimeException When `$throwOnError` is true and the path does not exist.
     */
    public function exists(
        bool $throwOnError = false,
    ): bool;

    /** Whether this path is an existing regular file. */
    public function isFile(): bool;

    /** Whether this path is an existing directory. */
    public function isDirectory(): bool;

    /** Whether this path is an existing symbolic link. */
    public function isLink(): bool;

    /** Whether this path exists and is readable. */
    public function isReadable(): bool;

    /** Whether this path exists and is writable. */
    public function isWritable(): bool;

    /** Whether this path is relative (not absolute on the current platform). */
    public function isRelative(): bool;

    /**
     * Whether the basename starts with `.`.
     *
     * Existence on disk is not required.
     */
    public function isDotPath(): bool;

    /**
     * Whether this path is an existing, hidden file (e.g. `.basename`).
     */
    public function isDotFile(): bool;

    /**
     * Whether this path is an existing, hidden directory (e.g. `.git`, `foo/.hidden`).
     */
    public function isDotDirectory(): bool;

    /**
     * Full pathname string.
     */
    public function getPathname(): string;

    /**
     * Directory portion of the pathname.
     */
    public function getPath(): string;

    /**
     * Basename without the extension.
     */
    public function getFilename(): string;

    /**
     * File extension, or an empty string when none is present.
     */
    public function getExtension(): string;

    /**
     * Resolves symbolic links and returns a normalized absolute path.
     *
     * @return ($throw is true ? string : false|string)
     *
     * @throws \Northrook\Contracts\Exceptions\RuntimeException when `$throw` is true and the path cannot be resolved
     */
    public function getRealPath(
        bool $throw = false,
    ): false|string;

    /**
     * Underlying {@see SplFileInfo} for this path.
     */
    public function getSplFileInfo(): SplFileInfo;

    /**
     * Atomically writes `$content` to this path.
     *
     * When `$makeRequiredDirectories` is true, creates the parent directory first.
     *
     * @param resource|string $content
     *
     * @return bool True on success, false on failure.
     */
    public function save(
        mixed $content,
        bool $makeRequiredDirectories = true,
    ): bool;

    /**
     * Creates the parent directory of this path when it does not exist.
     *
     * @return bool True when the directory exists or was created, false on failure.
     */
    public function mkdir(
        int $permissions = 0777,
        bool $recursive = true,
    ): bool;

    /**
     * Reads this path as a string.
     *
     * @return ($throw is true ? string : null|string)
     *
     * @throws RuntimeException When `$throw` is true and reading fails
     */
    public function getContents(
        bool $throw = false,
    ): null|string;

    /**
     * Copies this path to `$target` and returns a path instance for the destination.
     *
     * When `$alwaysOverwrite` is false, existing newer targets may be left untouched per the filesystem implementation.
     */
    public function copy(
        string|Stringable $target,
        bool $alwaysOverwrite = false,
    ): static;

    /**
     * Removes the file or directory at this path.
     *
     * @return bool True on success, false on failure.
     */
    public function remove(): bool;

    /**
     * Runs `glob()` patterns relative to this path's directory.
     *
     * @param string|string[] $pattern
     *
     * @return list<static>
     */
    public function glob(
        string|array $pattern,
        null|int $flags = null,
    ): array;
}

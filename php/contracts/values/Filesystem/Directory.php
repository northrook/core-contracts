<?php

declare(strict_types=1);

namespace Northrook\Filesystem;

use Northrook\FilesystemInterface;
use Northrook\InvalidArgumentException;
use Northrook\Normalize;
use Northrook\RuntimeException;

/**
 * Filesystem directory {@see Reference}.
 *
 * Directory-oriented listing and mutation over a path location. Validated
 * denoting string — no guarantee the directory exists unless constructed with
 * `$assert` or `$create`.
 *
 * Sibling of {@see Path} / {@see File}, not an {@see \SplFileInfo} /
 * {@see \DirectoryIterator} facade. Prefer this type for children, glob, ensure,
 * and sync rather than a generic path.
 *
 * {@see from()} validates string form only; use the constructor `$assert` flag
 * when the path must exist, or `$create` to ensure it exists.
 *
 *
 */
final readonly class Directory extends Path
{
    /**
     * Builds from `$path` after normalization.
     *
     * @param string|\Stringable|Path              $path
     * @param bool                                 $assert  When true, path must exist and be a directory
     * @param bool                                 $create  When true, create the directory recursively when missing
     * @param null|\Northrook\FilesystemInterface  $filesystem
     */
    public function __construct(
        string|\Stringable|Path  $path,
        bool                     $assert = false,
        bool                     $create = false,
        null|FilesystemInterface $filesystem = null,
    ) {
        if ($path instanceof Path) {
            $filesystem ??= $path->filesystem;
            $path       = $path->value;
        }

        parent::__construct($path, $filesystem);

        if ($create && ! $this->ensure()) {
            throw new RuntimeException(
                message: "Unable to create directory '{$this->value}'.",
                context: ['path' => $this->value],
            );
        }

        if ($assert && ! $this->isDirectory()) {
            throw new RuntimeException(
                message: "Path '{$this->value}' is not an existing directory.",
                context: ['path' => $this->value],
            );
        }
    }

    /**
     * System or process temporary directory.
     */
    public static function temporary(): self
    {
        // TODO: use Context
        $directory = \sys_get_temp_dir();

        if ($directory === '') {
            throw new RuntimeException(
                message: 'Unable to determine the system temporary directory.',
            );
        }

        return new self($directory);
    }

    /**
     * This location as a generic {@see Path}.
     */
    public function path(): Path
    {
        return $this->toPath();
    }

    /**
     * Parent directory.
     */
    public function parent(): self
    {
        return new self(
            path      : $this->dirname(),
            filesystem: $this->filesystem,
        );
    }

    /**
     * Child location under this directory as a generic {@see Path}.
     *
     * Does not require the child to exist. Prefer {@see file()} / {@see directory()}
     * when the typed view is known. `$segment` must stay under this directory
     * after traversal resolution — absolute segments and escapes throw.
     *
     * @throws InvalidArgumentException When `$segment` is empty, absolute, or escapes this directory
     */
    public function child(
        string|\Stringable $segment,
    ): Path {
        return new Path(
            path      : $this->pathUnder($segment),
            filesystem: $this->filesystem,
        );
    }

    /**
     * Child file path under this directory (shape only — no existence assert).
     *
     * @throws InvalidArgumentException When `$segment` is empty, absolute, or escapes this directory
     */
    public function file(
        string|\Stringable $segment,
    ): File {
        return new File(
            path      : $this->pathUnder($segment),
            filesystem: $this->filesystem,
        );
    }

    /**
     * Child directory path under this directory (shape only — no existence assert).
     *
     * @throws InvalidArgumentException When `$segment` is empty, absolute, or escapes this directory
     */
    public function directory(
        string|\Stringable $segment,
        bool               $assert = false,
        bool               $create = false,
    ): self {
        return new self(
            path      : $this->pathUnder($segment),
            assert    : $assert,
            create    : $create,
            filesystem: $this->filesystem,
        );
    }

    /**
     * Join `$segment` under this directory, resolving `..` without escaping the root.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException
     */
    private function pathUnder(
        string|\Stringable $segment,
    ): string {
        $segment = \string($segment);

        if ($segment === '' || Normalize::isAbsolutePath($segment)) {
            throw new InvalidArgumentException(
                message: 'Child segment must be a non-empty path relative to this directory.',
                context: [
                    'directory' => $this->value,
                    'name'      => 'segment',
                    'expected'  => 'relative path under this directory',
                    'received'  => $segment,
                ],
            );
        }

        $joined = Normalize::path(
            [$this->value, $segment],
            traversal: true,
            throwOnEmpty: true,
        );
        $path = new Path($joined, $this->filesystem);

        if (! $this->contains($path)) {
            throw new InvalidArgumentException(
                message: "Child segment '{$segment}' escapes directory '{$this->value}'.",
                context: [
                    'directory' => $this->value,
                    'resolved'  => $path->value,
                    'name'      => 'segment',
                    'expected'  => 'path under this directory',
                    'received'  => $segment,
                ],
            );
        }

        return $path->value;
    }

    /**
     * Whether this directory exists on disk.
     *
     * @throws RuntimeException When `$throwOnError` is true and the directory does not exist
     */
    public function exists(
        bool $throwOnError = false,
    ): bool {
        if ($this->isDirectory()) {
            return true;
        }

        if ($throwOnError) {
            throw new RuntimeException(
                message: "Directory '{$this->value}' does not exist.",
                context: ['path' => $this->value],
            );
        }

        return false;
    }

    /**
     * Whether this directory exists and contains no entries (ignoring `.` / `..`).
     */
    public function isEmpty(): bool
    {
        if (! $this->isDirectory()) {
            return false;
        }

        $entries = $this->listEntryPaths();

        // Listing failure is not emptiness (matches prior `scandir === false`).
        return $entries !== false && $entries === [];
    }

    /**
     * Whether `$path` is this directory or a descendant of it.
     */
    public function contains(
        string|\Stringable|Path|File|self $path,
    ): bool {
        try {
            $candidate = new Path(\string($path), $this->filesystem)->absolute()->value;
            $root      = $this->absolute()->value;
        }
        catch (\Throwable) {
            return false;
        }

        if ($candidate === $root) {
            return true;
        }

        $prefix = \rtrim($root, \DIR_SEP) . \DIR_SEP;

        return \str_starts_with($candidate, $prefix);
    }

    /**
     * Whether `$other` denotes the same canonical path string.
     */
    public function equals(
        self|Path|string|\Stringable $other,
    ): bool {
        return parent::equals($other);
    }

    // -------------------------------------------------------------------------
    // Listing
    // -------------------------------------------------------------------------

    /**
     * Immediate children as path values (files and directories).
     *
     * @return list<Path>
     */
    public function children(
        bool $includeDots = false,
    ): array {
        $entries = $this->listEntryPaths();

        if ($entries === false) {
            return [];
        }

        $result = [];

        foreach ($entries as $pathname) {
            $basename = \basename($pathname);

            if (! $includeDots && \str_starts_with($basename, '.')) {
                continue;
            }

            $result[] = new Path(
                path      : $pathname,
                filesystem: $this->filesystem,
            );
        }

        return $result;
    }

    /**
     * Immediate child files only.
     *
     * @return list<File>
     */
    public function files(
        bool $includeDots = false,
    ): array {
        $result = [];

        foreach ($this->children($includeDots) as $child) {
            if ($child->isFile()) {
                $result[] = new File(
                    path      : $child->value,
                    filesystem: $this->filesystem,
                );
            }
        }

        return $result;
    }

    /**
     * Immediate child directories only.
     *
     * @return list<self>
     */
    public function directories(
        bool $includeDots = false,
    ): array {
        $result = [];

        foreach ($this->children($includeDots) as $child) {
            if ($child->isDirectory()) {
                $result[] = new self(
                    path      : $child->value,
                    filesystem: $this->filesystem,
                );
            }
        }

        return $result;
    }

    /**
     * Glob patterns relative to this directory.
     *
     * @param string|list<string> $pattern
     *
     * @return list<Path>
     */
    public function glob(
        string|array $pattern,
        null|int     $flags = null,
    ): array {
        $patterns = [];

        foreach ((array) $pattern as $item) {
            if (Normalize::isAbsolutePath($item)) {
                $patterns[] = $item;
            }
            else {
                $patterns[] = Normalize::path(
                    [$this->value, $item],
                    traversal: true,
                    throwOnEmpty: true,
                );
            }
        }

        $matches = $this->filesystem->glob($patterns, $flags);

        $result = [];

        foreach ($matches as $match) {
            $result[] = new Path(
                path      : $match,
                filesystem: $this->filesystem,
            );
        }

        return $result;
    }

    /**
     * Recursive iterator over entries under this directory.
     *
     * Leaves-only: files (and non-directory entries) are yielded; directories are
     * descended into but not themselves yielded.
     *
     * Symbolic links are never followed — a symlink is yielded as a leaf even
     * when it points at a directory (same rule as sync/remove).
     *
     * @return \Traversable<int, Path>
     */
    public function walk(
        bool $includeDots = false,
    ): \Traversable {
        if (! $this->isDirectory()) {
            return;
        }

        yield from $this->walkListed($includeDots);
    }

    /**
     * Immediate entry pathnames under this directory.
     *
     * @return list<string>|false Full paths (no `.` / `..`); `false` on listing failure
     */
    private function listEntryPaths(): array|false
    {
        try {
            return $this->filesystem->listDirectory($this->value);
        }
        catch (FilesystemException) {
            return false;
        }
    }

    /**
     * Leaves-only walk via {@see FilesystemInterface::listDirectory()}.
     *
     * @return \Traversable<int, Path>
     */
    private function walkListed(
        bool $includeDots,
    ): \Traversable {
        $entries = $this->listEntryPaths();

        if ($entries === false) {
            return;
        }

        foreach ($entries as $pathname) {
            $path = new Path($pathname, $this->filesystem);

            if (! $includeDots && $path->isDot()) {
                continue;
            }

            // Match sync/remove: descend real directories only; symlink-dirs are leaves.
            if ($path->isDirectory() && ! $path->isLink()) {
                yield from new self(
                    path      : $pathname,
                    filesystem: $this->filesystem,
                )->walk($includeDots);

                continue;
            }

            yield $path;
        }
    }

    // -------------------------------------------------------------------------
    // Mutation
    // -------------------------------------------------------------------------

    /**
     * Ensure this directory exists (create when missing).
     *
     * Idempotent counterpart to {@see mkdir()} for "make sure it's there" call sites.
     *
     * @return bool `true` when it exists or was created
     */
    public function ensure(
        int  $permissions = 0777,
        bool $recursive = true,
    ): bool {
        if ($this->isDirectory()) {
            return true;
        }

        return $this->mkdir($permissions, $recursive);
    }

    /**
     * Create this directory.
     *
     * @return bool `true` on success, `false` on failure
     */
    public function mkdir(
        int  $permissions = 0777,
        bool $recursive = true,
    ): bool {
        // FilesystemInterface::createDirectory is always recursive; `$recursive`
        // is retained for call-site parity with native mkdir.
        if (! $recursive && $this->parent() !== null && ! $this->parent()->isDirectory()) {
            return false;
        }

        if ($this->isDirectory()) {
            return true;
        }

        try {
            $this->filesystem->createDirectory($this->value, $permissions);

            return $this->isDirectory();
        }
        catch (FilesystemException) {
            return false;
        }
    }

    /**
     * Copy this directory tree to `$target` and return the destination directory.
     *
     * When `$alwaysOverwrite` is false, existing newer targets may be left
     * untouched (mtime comparison), matching the filesystem collaborator.
     */
    public function copy(
        string|\Stringable|Path|self $target,
        bool                         $alwaysOverwrite = false,
    ): static {
        $destination = \string($target);

        $this->filesystem->syncDirectory(
            sourceDirectory     : $this->value,
            destinationDirectory: $destination,
            alwaysOverwrite     : $alwaysOverwrite,
        );

        return new self(
            path      : $destination,
            filesystem: $this->filesystem,
        );
    }

    /**
     * Synchronize this directory's contents into `$target`.
     *
     * @param bool $deleteMissing When true, remove entries in `$target` that are absent here
     */
    public function sync(
        string|\Stringable|Path|self $target,
        bool                         $alwaysOverwrite = false,
        bool                         $deleteMissing = false,
    ): self {
        $destination = \string($target);

        $this->filesystem->syncDirectory(
            sourceDirectory     : $this->value,
            destinationDirectory: $destination,
            alwaysOverwrite     : $alwaysOverwrite,
            deleteMissingFiles  : $deleteMissing,
        );

        return new self(
            path      : $destination,
            filesystem: $this->filesystem,
        );
    }

    /**
     * Move / rename this directory to `$target` and return the destination.
     */
    public function move(
        string|\Stringable|Path|self $target,
        bool                         $overwrite = false,
    ): static {
        return parent::move($target, $overwrite);
    }

    /**
     * Remove this directory.
     *
     * @param bool $recursive When true, delete contents recursively
     *
     * @return bool `true` on success, `false` on failure
     *
     * @throws RuntimeException When `$recursive` is false and the directory is not empty
     */
    public function remove(
        bool $recursive = true,
    ): bool {
        if (! $recursive && $this->isDirectory() && ! $this->isEmpty()) {
            throw new RuntimeException(
                message: "Directory '{$this->value}' is not empty.",
                context: ['path' => $this->value, 'recursive' => false],
            );
        }

        if (! $recursive) {
            return @\rmdir($this->value);
        }

        try {
            $this->filesystem->remove($this->value);

            return true;
        }
        catch (FilesystemException) {
            return false;
        }
    }
}

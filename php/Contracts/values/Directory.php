<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Filesystem directory {@see Reference}.
 *
 * Directory-oriented listing and mutation over a path location. Validated
 * denoting string — no guarantee the directory exists unless constructed with
 * `$assert`.
 *
 * Sibling of {@see Path} / {@see File}, not an {@see \SplFileInfo} /
 * {@see \DirectoryIterator} facade. Prefer this type for children, glob, ensure,
 * and sync rather than a generic path.
 *
 * {@see from()} validates string form only; use the constructor `$assert` flag
 * when the path must exist and be a directory.
 */
final readonly class Directory extends Path
{
    /**
     * Builds from `$path` after normalization.
     *
     * @param string|\Stringable|Path                        $path
     * @param bool                                           $assert  When true, path must exist and be a directory
     * @param null|\Northrook\Contracts\FilesystemInterface  $filesystem
     */
    public function __construct(
        string|\Stringable|Path  $path,
        bool                     $assert = false,
        null|FilesystemInterface $filesystem = null,
    ) {
        if ($path instanceof Path) {
            $filesystem ??= $path->filesystem;
            $path       = $path->value;
        }

        parent::__construct($path, $filesystem);

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
    ): self {
        return new self(
            path      : $this->pathUnder($segment),
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
        $segment = (string) $segment;

        if ($segment === '' || Normalize::isAbsolutePath($segment)) {
            throw new InvalidArgumentException(
                message : 'Child segment must be a non-empty path relative to this directory.',
                name    : 'segment',
                expected: 'relative path under this directory',
                received: $segment,
                context : ['directory' => $this->value],
            );
        }

        $joined = Normalize::path([$this->value, $segment], traversal: true);
        $path   = new Path($joined, $this->filesystem);

        if (! $this->contains($path)) {
            throw new InvalidArgumentException(
                message : "Child segment '{$segment}' escapes directory '{$this->value}'.",
                name    : 'segment',
                expected: 'path under this directory',
                received: $segment,
                context : [
                    'directory' => $this->value,
                    'resolved'  => $path->value,
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
            $candidate = new Path((string) $path, $this->filesystem)->absolute()->value;
            $root      = $this->absolute()->value;
        } catch (\Throwable) {
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
            } else {
                $patterns[] = Normalize::path([$this->value, $item], traversal: true);
            }
        }

        $matches = $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): array => $fs->glob($patterns, $flags),
            fallback: function() use ($patterns, $flags): array {
                $result = [];

                foreach ($patterns as $item) {
                    $matched = $flags === null ? \glob($item) : \glob($item, $flags);

                    if ($matched === false) {
                        continue;
                    }

                    foreach ($matched as $match) {
                        $result[] = $match;
                    }
                }

                return \array_values(\array_unique($result));
            },
        );

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

        if ($this->filesystem !== null) {
            yield from $this->walkListed($includeDots);

            return;
        }

        $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->value, $flags),
        );

        foreach ($iterator as $pathname) {
            if (! \is_string($pathname)) {
                continue;
            }

            $path = new Path($pathname, $this->filesystem);

            if (! $includeDots && $path->isDot()) {
                continue;
            }

            yield $path;
        }
    }

    /**
     * Immediate entry pathnames under this directory.
     *
     * @return list<string>|false Full paths (no `.` / `..`); `false` on listing failure
     */
    private function listEntryPaths(): array|false
    {
        return $this->filesystem(
            __METHOD__,
            filesystem: function(
                FilesystemInterface $fs,
            ): array|false {
                try {
                    return $fs->listDirectory($this->value);
                } catch (FilesystemException) {
                    return false;
                }
            },
            fallback: function(): array|false {
                $entries = @\scandir($this->value);

                if ($entries === false) {
                    return false;
                }

                $paths = [];

                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }

                    $paths[] = $this->value . \DIR_SEP . $entry;
                }

                return $paths;
            },
        );
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
        if ($this->isDirectory()) {
            return true;
        }

        // FilesystemInterface::createDirectory is always recursive; `$recursive`
        // is retained for call-site parity with native mkdir.
        return $this->filesystem(
            __METHOD__,
            filesystem: function(
                FilesystemInterface $fs,
            ) use ($permissions): bool {
                try {
                    $fs->createDirectory($this->value, $permissions);

                    return $this->isDirectory();
                } catch (FilesystemException) {
                    return false;
                }
            },
            fallback: function() use ($permissions, $recursive): bool {
                if (@\mkdir($this->value, $permissions, $recursive) || $this->isDirectory()) {
                    return $this->isDirectory();
                }

                return false;
            },
        );
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
        $destination = (string) $target;

        $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs) => $fs->syncDirectory(
                sourceDirectory     : $this->value,
                destinationDirectory: $destination,
                alwaysOverwrite     : $alwaysOverwrite,
            ),
            fallback: fn() => self::nativeSyncDirectory(
                $this->value,
                $destination,
                $alwaysOverwrite,
                deleteMissing: false,
            ),
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
        $destination = (string) $target;

        $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs) => $fs->syncDirectory(
                sourceDirectory     : $this->value,
                destinationDirectory: $destination,
                alwaysOverwrite     : $alwaysOverwrite,
                deleteMissingFiles  : $deleteMissing,
            ),
            fallback: fn() => self::nativeSyncDirectory(
                $this->value,
                $destination,
                $alwaysOverwrite,
                $deleteMissing,
            ),
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

        return $this->filesystem(
            __METHOD__,
            filesystem: function(
                FilesystemInterface $fs,
            ): bool {
                try {
                    $fs->remove($this->value);

                    return true;
                } catch (FilesystemException) {
                    return false;
                }
            },
            fallback: function() use ($recursive): bool {
                if (! $this->isDirectory()) {
                    return false;
                }

                if ($recursive) {
                    return parent::remove();
                }

                return @\rmdir($this->value);
            },
        );
    }

    /**
     * Native recursive directory sync (no {@see FilesystemInterface}).
     *
     * @throws FilesystemException
     */
    private static function nativeSyncDirectory(
        string $source,
        string $destination,
        bool   $alwaysOverwrite,
        bool   $deleteMissing,
    ): void {
        if (! \is_dir($source)) {
            throw new FilesystemException(
                message: "Unable to sync '{$source}': not a directory.",
                path   : $source,
            );
        }

        if (! \is_dir($destination)) {
            if (! @\mkdir($destination, 0777, true) && ! \is_dir($destination)) {
                throw new FilesystemException(
                    message: "Unable to create destination directory '{$destination}'.",
                    path   : $destination,
                );
            }
        }

        $entries = @\scandir($source);

        if ($entries === false) {
            throw new FilesystemException(
                message: "Unable to read directory '{$source}'.",
                path   : $source,
            );
        }

        $seen = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $seen[$entry] = true;
            $from         = $source . \DIR_SEP . $entry;
            $to           = $destination . \DIR_SEP . $entry;

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
                    if (! self::nativeRemove($to)) {
                        throw new FilesystemException(
                            message: "Unable to replace '{$to}' with a symlink.",
                            path   : $to,
                        );
                    }
                }

                $parent = \dirname($to);

                if ($parent !== '' && $parent !== '.' && ! \is_dir($parent)) {
                    if (! @\mkdir($parent, 0777, true) && ! \is_dir($parent)) {
                        throw new FilesystemException(
                            message: "Unable to create parent directory '{$parent}'.",
                            path   : $parent,
                        );
                    }
                }

                if (! @\symlink($linkTarget, $to)) {
                    throw new FilesystemException(
                        message: "Unable to create symlink '{$to}' -> '{$linkTarget}'.",
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
                self::nativeSyncDirectory($from, $to, $alwaysOverwrite, $deleteMissing);

                continue;
            }

            if (! $alwaysOverwrite && \is_file($to) && \filemtime($to) >= \filemtime($from)) {
                continue;
            }

            if (\file_exists($to) || \is_link($to)) {
                if (! self::nativeRemove($to)) {
                    throw new FilesystemException(
                        message: "Unable to replace '{$to}' with a file.",
                        path   : $to,
                    );
                }
            }

            $parent = \dirname($to);

            if ($parent !== '' && $parent !== '.' && ! \is_dir($parent)) {
                if (! @\mkdir($parent, 0777, true) && ! \is_dir($parent)) {
                    throw new FilesystemException(
                        message: "Unable to create parent directory '{$parent}'.",
                        path   : $parent,
                    );
                }
            }

            if (! \copy($from, $to)) {
                throw new FilesystemException(
                    message: "Unable to copy '{$from}' to '{$to}'.",
                    context: [
                        'source' => $from,
                        'target' => $to,
                    ],
                );
            }
        }

        if (! $deleteMissing) {
            return;
        }

        $destEntries = @\scandir($destination);

        if ($destEntries === false) {
            throw new FilesystemException(
                message: "Unable to read directory '{$destination}'.",
                path   : $destination,
            );
        }

        foreach ($destEntries as $entry) {
            if ($entry === '.' || $entry === '..' || isset($seen[$entry])) {
                continue;
            }

            $extra = $destination . \DIR_SEP . $entry;

            if (! self::nativeRemove($extra)) {
                throw new FilesystemException(
                    message: "Unable to remove missing sync target '{$extra}'.",
                    path   : $extra,
                );
            }
        }
    }
}

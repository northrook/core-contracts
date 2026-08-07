<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Filesystem path {@see Reference}.
 *
 * Location identity and structural path operations over a local path string.
 * Validated denoting string only — no guarantee the path exists on disk.
 *
 * Not a facade over {@see \SplFileInfo} (intended internal helper only). Prefer
 * {@see File} / {@see Directory} when the path is known to be a file or
 * directory and typed I/O or listing matters.
 *
 * Replaces the deprecated {@see PathInterface} surface under immutable returns.
 *
 * Optional {@see FilesystemInterface} collaborator: when present, all I/O and
 * existence predicates go through it; when null, simple native PHP is used.
 */
readonly class Path implements Reference
{
    use ReferenceTrait;
    use FilesystemTrait;

    /**
     * Canonical filesystem path string after {@see normalize()}.
     *
     * @var non-empty-string
     */
    public string $value;

    /**
     * Builds from `$path` after normalization.
     *
     * @param string|\Stringable $path Local filesystem path (not a URI shape)
     *
     * @throws InvalidArgumentException When `$path` is empty, too long, or URI-shaped
     */
    public function __construct(
        string|\Stringable       $path,
        null|FilesystemInterface $filesystem = null,
    ) {
        $this->filesystem = $filesystem;
        $this->value      = static::normalize($path);
    }

    /**
     * Canonical path string (separators collapsed, `.` segments dropped, etc.).
     *
     * {@inheritDoc}
     *
     * @return non-empty-string
     */
    public static function normalize(
        string|\Stringable $value,
    ): string {
        $string = (string) $value;

        if ($string === '') {
            throw new InvalidArgumentException(
                message : 'Path cannot be empty.',
                name    : 'path',
                expected: 'non-empty filesystem path',
                received: $value,
            );
        }

        $schemeEnd = \strpos($string, '://');

        if ($schemeEnd !== false && \is_path_scheme(\substr($string, 0, $schemeEnd))) {
            throw new InvalidArgumentException(
                message : 'Path cannot be URI-shaped.',
                name    : 'path',
                expected: 'local filesystem path',
                received: $value,
            );
        }

        try {
            $normalized = Normalize::path(
                path        : $string,
                traversal   : true,
                throwOnEmpty: false,
            );
        } catch (\Throwable $exception) {
            if ($exception instanceof InvalidArgumentException || $exception instanceof FilesystemException) {
                throw $exception;
            }

            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                name    : 'path',
                expected: 'valid filesystem path',
                received: $value,
                previous: $exception,
            );
        }

        if ($normalized === '') {
            throw new InvalidArgumentException(
                message : 'Path normalized to an empty string.',
                name    : 'path',
                expected: 'non-empty filesystem path',
                received: $value,
            );
        }

        return $normalized;
    }

    /**
     * @return array{path: non-empty-string}
     */
    protected function filesystemContext(): array
    {
        return ['path' => $this->value];
    }

    // -------------------------------------------------------------------------
    // Structure
    // -------------------------------------------------------------------------

    /**
     * Parent directory as a typed {@see Directory}.
     */
    public function parent(): Directory
    {
        return new Directory(
            path      : $this->dirname(),
            filesystem: $this->filesystem,
        );
    }

    /**
     * Join one or more segments onto this path as a generic {@see Path}.
     */
    public function join(
        string|\Stringable ...$segments,
    ): Path {
        $parts = [$this->value];

        foreach ($segments as $segment) {
            $part = (string) $segment;

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return new Path(
            path      : Normalize::path($parts, traversal: true),
            filesystem: $this->filesystem,
        );
    }

    /**
     * Full pathname — same string as {@see $value}.
     */
    public function pathname(): string
    {
        return $this->value;
    }

    /**
     * Directory portion of the pathname (string, not a {@see Directory}).
     *
     * Use {@see asDirectory()} or {@see parent()} when you need a typed path.
     *
     * @return non-empty-string
     */
    public function dirname(): string
    {
        $dirname = \dirname($this->value);

        return $dirname === '' ? '.' : $dirname;
    }

    /**
     * Final path segment (filename or directory name), including extension.
     *
     * @return non-empty-string
     */
    public function basename(): string
    {
        $basename = \basename($this->value);

        return $basename === '' ? $this->value : $basename;
    }

    /**
     * Basename without the extension.
     */
    public function filename(): string
    {
        return \pathinfo($this->value, \PATHINFO_FILENAME);
    }

    /**
     * Extension without a leading `.`, or an empty string when none is present.
     */
    public function extension(): string
    {
        return \pathinfo($this->value, \PATHINFO_EXTENSION);
    }

    /**
     * Return a copy with the extension replaced or cleared.
     *
     * @param string $extension Without leading `.`; empty string clears the extension
     */
    public function withExtension(
        string $extension,
    ): static {
        $filename  = $this->filename();
        $extension = \ltrim($extension, '.');
        $basename  = $extension === '' ? $filename : $filename . '.' . $extension;

        return new static(
            path      : $this->joinDirname($basename),
            filesystem: $this->filesystem,
        );
    }

    /**
     * Return a copy with the final path segment replaced.
     */
    public function withBasename(
        string $basename,
    ): static {
        return new static(
            path      : $this->joinDirname($basename),
            filesystem: $this->filesystem,
        );
    }

    /**
     * Join `$basename` under this path's dirname without turning `/` into a UNC root.
     */
    private function joinDirname(
        string $basename,
    ): string {
        $dirname = $this->dirname();

        if ($dirname === '.') {
            return $basename;
        }

        return \rtrim($dirname, \DIR_SEP) . \DIR_SEP . $basename;
    }

    // -------------------------------------------------------------------------
    // Predicates
    // -------------------------------------------------------------------------

    /**
     * Whether this path exists on disk.
     *
     * @throws RuntimeException When `$throwOnError` is true and the path does not exist
     */
    public function exists(
        bool $throwOnError = false,
    ): bool {
        $exists = $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): bool => $fs->fileExists($this->value),
            fallback: fn(): bool => \file_exists($this->value),
        );

        if ($exists) {
            return true;
        }

        if ($throwOnError) {
            throw new RuntimeException(
                message: "Path '{$this->value}' does not exist.",
                context: ['path' => $this->value],
            );
        }

        return false;
    }

    /**
     * Whether this path is an existing regular file.
     *
     * @phpstan-impure
     */
    public function isFile(): bool
    {
        return $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): bool => $fs->isFile($this->value),
            fallback: fn(): bool => \is_file($this->value),
        );
    }

    /**
     * Whether this path is an existing directory.
     *
     * @phpstan-impure
     */
    public function isDirectory(): bool
    {
        return $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): bool => $fs->isDirectory($this->value),
            fallback: fn(): bool => \is_dir($this->value),
        );
    }

    /**
     * Whether this path is an existing symbolic link.
     *
     * @phpstan-impure
     */
    public function isLink(): bool
    {
        return $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): bool => $fs->isLink($this->value),
            fallback: fn(): bool => \is_link($this->value),
        );
    }

    /**
     * Whether this path exists and is readable.
     *
     * @phpstan-impure
     */
    public function isReadable(): bool
    {
        return $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): bool => $fs->isReadable($this->value),
            fallback: fn(): bool => \is_readable($this->value),
        );
    }

    /**
     * Whether this path exists and is writable.
     *
     * @phpstan-impure
     */
    public function isWritable(): bool
    {
        return $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): bool => $fs->isWritable($this->value),
            fallback: fn(): bool => \is_writable($this->value),
        );
    }

    /**
     * Whether this path is absolute on the current platform.
     */
    public function isAbsolute(): bool
    {
        return Normalize::isAbsolutePath($this->value);
    }

    /**
     * Whether this path is relative (not absolute on the current platform).
     */
    public function isRelative(): bool
    {
        return ! $this->isAbsolute();
    }

    /**
     * Whether the basename starts with `.` (hidden-name convention).
     *
     * Existence on disk is not required.
     */
    public function isDot(): bool
    {
        $basename = $this->basename();

        return $basename !== '' && $basename[0] === '.';
    }

    /**
     * Whether `$other` denotes the same canonical path string.
     */
    public function equals(
        self|string|\Stringable $other,
    ): bool {
        if ($other instanceof self) {
            return $this->value === $other->value;
        }

        try {
            return $this->value === static::normalize($other);
        } catch (\Throwable) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Resolution
    // -------------------------------------------------------------------------

    /**
     * Resolved absolute path with symlinks expanded, or false when unresolvable.
     *
     * @return ($throw is true ? non-empty-string : false|non-empty-string)
     *
     * @throws RuntimeException When `$throw` is true and the path cannot be resolved
     */
    public function realPath(
        bool $throw = false,
    ): false|string {
        $resolved = $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): false|string => $fs->resolvePath($this->value) ?? false,
            fallback: fn(): false|string => \realpath($this->value),
        );

        if ($resolved !== false && $resolved !== '') {
            return $resolved;
        }

        if ($throw) {
            throw new RuntimeException(
                message: "Unable to resolve real path for '{$this->value}'.",
                context: ['path' => $this->value],
            );
        }

        return false;
    }

    /**
     * Absolute form of this path without requiring it to exist on disk.
     */
    public function absolute(): static
    {
        if ($this->isAbsolute()) {
            return $this;
        }

        $cwd = \getcwd();

        if ($cwd === false) {
            throw new RuntimeException(
                message: 'Unable to determine the current working directory.',
                context: ['path' => $this->value],
            );
        }

        return new static(
            path      : Normalize::path([$cwd, $this->value], traversal: true),
            filesystem: $this->filesystem,
        );
    }

    /**
     * This path expressed relative to `$from`.
     */
    public function relativeTo(
        self|string|\Stringable $from,
    ): static {
        $fromPath = new self((string) $from, $this->filesystem)->absolute()->value;
        $toPath   = $this->absolute()->value;

        if ($fromPath === $toPath) {
            return new static(
                path      : './',
                filesystem: $this->filesystem,
            );
        }

        // Re-index after array_filter — absolute paths leave a hole at key 0.
        $fromParts = \rtrim($fromPath, \DIR_SEP)
            |> ( fn($x) => \explode(\DIR_SEP, $x) )
            |> ( fn($x) => \array_values(\array_filter($x, static fn(string $p): bool => $p !== '')) );

        $toParts = \rtrim($toPath, \DIR_SEP)
            |> ( fn($x) => \explode(\DIR_SEP, $x) )
            |> ( fn($x) => \array_values(\array_filter($x, static fn(string $p): bool => $p !== '')) );

        $shared = 0;
        $limit  = \min(\count($fromParts), \count($toParts));

        while ($shared < $limit && $fromParts[$shared] === $toParts[$shared]) {
            $shared++;
        }

        $up       = \array_fill(0, \count($fromParts) - $shared, '..');
        $down     = \array_slice($toParts, $shared);
        $relative = \implode(\DIR_SEP, [...$up, ...$down]);

        return new static(
            path      : $relative === '' ? './' : $relative,
            filesystem: $this->filesystem,
        );
    }

    // -------------------------------------------------------------------------
    // Typed views
    // -------------------------------------------------------------------------

    /**
     * View this location as a {@see File}.
     *
     * @param bool $assert When true, the path must exist and be a regular file
     *
     * @throws RuntimeException When `$assert` and the path is not an existing file
     */
    public function asFile(
        bool $assert = false,
    ): File {
        return new File(
            path      : $this->value,
            assert    : $assert,
            filesystem: $this->filesystem,
        );
    }

    /**
     * View this location as a {@see Directory}.
     *
     * @param bool $assert When true, the path must exist and be a directory
     *
     * @throws RuntimeException When `$assert` and the path is not an existing directory
     */
    public function asDirectory(
        bool $assert = false,
    ): Directory {
        return new Directory(
            path      : $this->value,
            assert    : $assert,
            filesystem: $this->filesystem,
        );
    }

    /**
     * This location as a generic {@see Path} (never a subclass instance).
     */
    public function toPath(): Path
    {
        return new Path(
            path      : $this->value,
            filesystem: $this->filesystem,
        );
    }

    /**
     * `file://` {@see Uri} for this path.
     */
    public function toUri(): Uri
    {
        $absolute = $this->absolute()->value;

        if (! \str_starts_with($absolute, '/')) {
            $absolute = '/' . \strtr($absolute, '\\', '/');
        }

        return new Uri('file://' . $absolute);
    }

    // -------------------------------------------------------------------------
    // Filesystem
    // -------------------------------------------------------------------------

    /**
     * Update access and modification times.
     *
     * Integer arguments are Unix timestamps in seconds. Omit both to touch "now".
     *
     * @return bool `true` on success, `false` on failure
     */
    public function touch(
        null|Timestamp|int $modifiedTime = null,
        null|Timestamp|int $accessTime = null,
    ): bool {
        $mtime = self::unixSeconds($modifiedTime);
        $atime = self::unixSeconds($accessTime);

        return $this->filesystem(
            __METHOD__,
            filesystem: function(
                FilesystemInterface $fs,
            ) use ($mtime, $atime): bool {
                try {
                    $fs->touch($this->value, $mtime, $atime);

                    return true;
                } catch (FilesystemException) {
                    return false;
                }
            },
            fallback: function() use ($mtime, $atime): bool {
                if ($mtime === null && $atime === null) {
                    return \touch($this->value);
                }

                return \touch(
                    $this->value,
                    $mtime ?? \time(),
                    $atime ?? $mtime ?? \time(),
                );
            },
        );
    }

    /**
     * Copy to `$target` and return a path for the destination.
     *
     * When `$alwaysOverwrite` is false, existing newer targets may be left
     * untouched per the filesystem implementation.
     */
    public function copy(
        string|\Stringable|self $target,
        bool                    $alwaysOverwrite = false,
    ): static {
        $destination = (string) $target;

        $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs) => $fs->copyFile(
                $this->value,
                $destination,
                $alwaysOverwrite,
            ),
            fallback: function() use ($destination, $alwaysOverwrite): void {
                if (
                    ! $alwaysOverwrite
                    && \is_file($destination)
                    && \filemtime($destination) >= \filemtime($this->value)
                ) {
                    return;
                }

                if (! \copy($this->value, $destination)) {
                    throw new FilesystemException(
                        message: "Unable to copy '{$this->value}' to '{$destination}'.",
                        context: [
                            'source' => $this->value,
                            'target' => $destination,
                        ],
                    );
                }
            },
        );

        return new static(
            path      : $destination,
            filesystem: $this->filesystem,
        );
    }

    /**
     * Move / rename to `$target` and return a path for the destination.
     */
    public function move(
        string|\Stringable|self $target,
        bool                    $overwrite = false,
    ): static {
        $destination = (string) $target;

        $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs) => $fs->move(
                $this->value,
                $destination,
                $overwrite,
            ),
            fallback: function() use ($destination, $overwrite): void {
                if (\file_exists($destination)) {
                    if (! $overwrite) {
                        throw new FilesystemException(
                            message: "Unable to move '{$this->value}' to '{$destination}': target exists.",
                            context: [
                                'source' => $this->value,
                                'target' => $destination,
                            ],
                        );
                    }

                    // Use nativeRemove so symlink→dir unlinks the link only
                    // (nativeRemoveTree would follow and wipe the real target).
                    if (! self::nativeRemove($destination)) {
                        throw new FilesystemException(
                            message: "Unable to replace target '{$destination}'.",
                            context: ['target' => $destination],
                        );
                    }
                }

                if (! \rename($this->value, $destination)) {
                    throw new FilesystemException(
                        message: "Unable to move '{$this->value}' to '{$destination}'.",
                        context: [
                            'source' => $this->value,
                            'target' => $destination,
                        ],
                    );
                }
            },
        );

        return new static(
            path      : $destination,
            filesystem: $this->filesystem,
        );
    }

    /**
     * Remove the file or directory at this path.
     *
     * @return bool `true` on success, `false` on failure
     */
    public function remove(): bool
    {
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
            fallback: fn(): bool => self::nativeRemove($this->value),
        );
    }

    /**
     * Glob patterns relative to this path.
     *
     * When this path is an existing file, patterns are relative to its dirname.
     *
     * @param string|list<string> $pattern
     *
     * @return list<static>
     */
    public function glob(
        string|array $pattern,
        null|int     $flags = null,
    ): array {
        $base     = $this->isFile() ? $this->dirname() : $this->value;
        $patterns = [];

        foreach ((array) $pattern as $item) {
            if (Normalize::isAbsolutePath($item)) {
                $patterns[] = $item;
            } else {
                $patterns[] = Normalize::path([$base, $item], traversal: true);
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
            $result[] = new static(
                path      : $match,
                filesystem: $this->filesystem,
            );
        }

        return $result;
    }

    protected static function nativeRemove(
        string $path,
    ): bool {
        if (\is_link($path) || \is_file($path)) {
            return @\unlink($path);
        }

        if (\is_dir($path)) {
            return self::nativeRemoveTree($path);
        }

        return false;
    }

    protected static function nativeRemoveTree(
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
                if (! self::nativeRemoveTree($child)) {
                    return false;
                }
            } elseif (! @\unlink($child)) {
                return false;
            }
        }

        return @\rmdir($directory);
    }

    /**
     * @return null|int Unix seconds
     */
    protected static function unixSeconds(
        null|Timestamp|int $time,
    ): null|int {
        if ($time === null) {
            return null;
        }

        if ($time instanceof Timestamp) {
            return \intdiv($time->number, 1000);
        }

        return $time;
    }
}

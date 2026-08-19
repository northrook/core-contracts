<?php

declare(strict_types=1);

namespace Northrook\Filesystem;

use Northrook\FilesystemInterface;
use Northrook\InvalidArgumentException;
use Northrook\RuntimeException;
use Northrook\Timestamp;

/**
 * Filesystem file {@see Reference}.
 *
 * File-oriented I/O and metadata over a path location. Validated denoting
 * string — no guarantee the file exists unless constructed with `$assert`.
 *
 * Sibling of {@see Path} / {@see Directory}, not an {@see \SplFileInfo} facade.
 * Prefer this type when callers need read/write/size rather than a generic path.
 *
 * {@see from()} validates string form only; use the constructor `$assert` flag
 * when the path must exist and be a regular file.
 *
 *
 */
final readonly class File extends Path
{
    /**
     * Builds from `$path` after normalization.
     *
     * @param string|\Stringable|Path              $path
     * @param bool                                 $assert  When true, path must exist and be a regular file
     * @param null|\Northrook\FilesystemInterface  $filesystem
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

        if ($assert && ! $this->isFile()) {
            throw new RuntimeException(
                message: "Path '{$this->value}' is not an existing regular file.",
                context: ['path' => $this->value],
            );
        }
    }

    /**
     * Create a temporary file and return it.
     *
     * `$prefix` / `$suffix` are filename fragments only — directory separators
     * and escapes are rejected. The result always stays under `$directory`
     * (or the system temp dir when omitted).
     *
     * @param non-empty-string                  $prefix     Filename prefix
     * @param string                            $suffix     Filename suffix (e.g. `.tmp`)
     * @param null|string|\Stringable|Directory $directory  Parent directory; system temp when null
     * @param null|FilesystemInterface          $filesystem Collaborator; taken from `$directory` when it is a {@see Directory}
     *
     * @throws InvalidArgumentException When `$prefix` / `$suffix` are empty (prefix) or contain path separators
     * @throws RuntimeException When the file cannot be created, or a filesystem collaborator returns a path outside `$directory`
     */
    public static function temporary(
        string                            $prefix = 'nr_',
        string                            $suffix = '',
        null|string|\Stringable|Directory $directory = null,
        null|FilesystemInterface          $filesystem = null,
    ): self {
        self::assertTemporaryNameFragment($prefix, 'prefix', allowEmpty: false);
        self::assertTemporaryNameFragment($suffix, 'suffix', allowEmpty: true);

        if ($directory instanceof Directory) {
            $filesystem ??= $directory->filesystem;
            $parent     = $directory;
        } else {
            $parent = new Directory(
                $directory === null ? \sys_get_temp_dir() : (string) $directory,
                filesystem: $filesystem,
            );
        }

        $fs   = $filesystem ?? $parent->filesystem;
        $path = $fs->createTemporaryFile($parent->value, $prefix, $suffix);

        if (! $parent->contains($path)) {
            throw new RuntimeException(
                message: "Temporary file '{$path}' escapes directory '{$parent->value}'.",
                context: [
                    'path'      => $path,
                    'prefix'    => $prefix,
                    'suffix'    => $suffix,
                    'directory' => $parent->value,
                ],
            );
        }

        return new self($path, assert: true, filesystem: $fs);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertTemporaryNameFragment(
        string $value,
        string $argument,
        bool   $allowEmpty,
    ): void {
        if ($value === '') {
            if ($allowEmpty) {
                return;
            }

            throw new InvalidArgumentException(
                message: "Temporary file {$argument} must be a non-empty filename fragment.",
                context: [
                    'name'     => $argument,
                    'expected' => 'non-empty filename fragment',
                    'received' => $value,
                ],
            );
        }

        if (\str_contains($value, '/') || \str_contains($value, '\\') || \str_contains($value, "\0")) {
            throw new InvalidArgumentException(
                message: "Temporary file {$argument} must be a filename fragment, not a path.",
                context: [
                    'name'     => $argument,
                    'expected' => 'filename fragment without directory separators',
                    'received' => $value,
                ],
            );
        }
    }

    /**
     * This location as a generic {@see Path}.
     */
    public function path(): Path
    {
        return $this->toPath();
    }

    /**
     * Whether this file exists on disk.
     *
     * @throws RuntimeException When `$throwOnError` is true and the file does not exist
     */
    public function exists(
        bool $throwOnError = false,
    ): bool {
        if ($this->isFile()) {
            return true;
        }

        if ($throwOnError) {
            throw new RuntimeException(
                message: "File '{$this->value}' does not exist.",
                context: ['path' => $this->value],
            );
        }

        return false;
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
    // Metadata
    // -------------------------------------------------------------------------

    /**
     * File size in bytes.
     *
     * @throws RuntimeException When the file cannot be stat'd
     */
    public function size(): int
    {
        try {
            return $this->filesystem->fileSize($this->value);
        } catch (FilesystemException $exception) {
            throw new RuntimeException(
                message : "Unable to determine size of '{$this->value}'.",
                context : ['path' => $this->value],
                previous: $exception,
            );
        }
    }

    /**
     * Last modification time.
     *
     * @throws RuntimeException When the file cannot be stat'd
     */
    public function modifiedAt(): Timestamp
    {
        try {
            $mtime = $this->filesystem->modifiedTime($this->value);
        } catch (FilesystemException $exception) {
            throw new RuntimeException(
                message : "Unable to determine modification time of '{$this->value}'.",
                context : ['path' => $this->value],
                previous: $exception,
            );
        }

        return new Timestamp($mtime * 1000);
    }

    /**
     * Creation / birth time when the platform exposes it.
     *
     * Falls back to inode change time ({@see \filectime()}) on platforms
     * without a dedicated birth-time API.
     *
     * @throws RuntimeException When the timestamp is unavailable or the file cannot be stat'd
     */
    public function createdAt(): Timestamp
    {
        try {
            $ctime = $this->filesystem->createdTime($this->value);
        } catch (FilesystemException $exception) {
            throw new RuntimeException(
                message : "Unable to determine creation time of '{$this->value}'.",
                context : ['path' => $this->value],
                previous: $exception,
            );
        }

        return new Timestamp($ctime * 1000);
    }

    /**
     * Detected MIME type, or null when unknown / undetectable.
     */
    public function mimeType(): null|string
    {
        return $this->filesystem->mimeType($this->value);
    }

    // -------------------------------------------------------------------------
    // I/O
    // -------------------------------------------------------------------------

    /**
     * Read the entire file as a string.
     *
     * @return ($throw is true ? string : null|string)
     *
     * @throws RuntimeException When `$throw` is true and reading fails
     */
    public function read(
        bool $throw = true,
    ): null|string {
        try {
            return $this->filesystem->readFile($this->value);
        } catch (FilesystemException $exception) {
            if ($throw) {
                throw new RuntimeException(
                    message : "Unable to read file '{$this->value}'.",
                    context : ['path' => $this->value],
                    previous: $exception,
                );
            }

            return null;
        }
    }

    /**
     * Write `$content` to this path using atomic write via the filesystem collaborator.
     *
     * When `$makeParentDirectory` is true, creates the parent directory first.
     *
     * @param resource|string $content
     *
     * @return bool `true` on success, `false` on failure
     */
    public function write(
        mixed $content,
        bool  $makeParentDirectory = true,
    ): bool {
        if (\is_resource($content)) {
            $content = \stream_get_contents($content);

            if ($content === false) {
                return false;
            }
        }

        if (! \is_string($content)) {
            throw new InvalidArgumentException(
                context: [
                    'name'     => 'content',
                    'expected' => 'string|resource',
                    'received' => $content,
                ],
            );
        }

        try {
            if ($makeParentDirectory) {
                $this->filesystem->createParentDirectory($this->value);
            }

            $this->filesystem->writeFileAtomically($this->value, $content);

            return true;
        } catch (FilesystemException) {
            return false;
        }
    }

    /**
     * Append `$content` to this file.
     *
     * @param resource|string $content
     *
     * @return bool `true` on success, `false` on failure
     */
    public function append(
        mixed $content,
        bool  $lock = false,
    ): bool {
        if (\is_resource($content)) {
            $content = \stream_get_contents($content);

            if ($content === false) {
                return false;
            }
        }

        if (! \is_string($content)) {
            throw new InvalidArgumentException(
                context: [
                    'name'     => 'content',
                    'expected' => 'string|resource',
                    'received' => $content,
                ],
            );
        }

        try {
            $this->filesystem->appendToFile($this->value, $content, $lock);

            return true;
        } catch (FilesystemException) {
            return false;
        }
    }

    /**
     * Copy to `$target` and return a {@see File} for the destination.
     *
     * When `$alwaysOverwrite` is false, existing newer targets may be left
     * untouched per the filesystem implementation.
     */
    public function copy(
        string|\Stringable|Path|self $target,
        bool                         $alwaysOverwrite = false,
    ): static {
        return parent::copy($target, $alwaysOverwrite);
    }

    /**
     * Move / rename to `$target` and return a {@see File} for the destination.
     */
    public function move(
        string|\Stringable|Path|self $target,
        bool                         $overwrite = false,
    ): static {
        return parent::move($target, $overwrite);
    }
}

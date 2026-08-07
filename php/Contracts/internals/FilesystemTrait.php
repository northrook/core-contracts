<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Dual-dispatch helper for an optional {@see FilesystemInterface} collaborator.
 *
 * When `$filesystem` is set, I/O always goes through it. When null, callers may
 * supply a simple native fallback via `$native`; omitting `$native` throws
 * {@see DependencyException}.
 */
trait FilesystemTrait
{
    protected readonly null|FilesystemInterface $filesystem;

    /**
     * @template T
     *
     * @param callable(FilesystemInterface): T  $filesystem
     * @param null|callable(): T                $fallback  null = require collaborator
     *
     * @return T
     *
     * @throws DependencyException When no collaborator is set and `$native` is null
     */
    final protected function filesystem(
        string        $method,
        callable      $filesystem,
        null|callable $fallback = null,
    ): mixed {
        if ($this->filesystem !== null) {
            return $filesystem($this->filesystem);
        }

        if ($fallback !== null) {
            return $fallback();
        }

        throw new DependencyException(
            message: "`{$method}` requires a FilesystemInterface instance.",
            context: [
                'method' => $method,
                'class'  => static::class,
                ...$this->filesystemContext(),
            ],
        );
    }

    /**
     * Extra context merged into {@see DependencyException} when the collaborator is missing.
     *
     * @return array<string, mixed>
     */
    protected function filesystemContext(): array
    {
        return [];
    }
}

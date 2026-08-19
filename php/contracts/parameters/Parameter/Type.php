<?php

declare(strict_types=1);

namespace Northrook\Parameter;

use Northrook\InvalidArgumentException;
use Northrook\RuntimeException;

/**
 * Allowed shape of a parameter value.
 *
 * Each case is a predicate. {@see validate()} dispatches to the matching
 * checker. Disk existence and path normalization are out of scope.
 *
 * @phpstan-import-type ParameterValue from \Northrook\Parameter
 *
 */
enum Type
{
    /**
     * @var array<"value"|"path"|"directory"|"file"|"setting", self>
     */
    public const array CASES = [
        'value'     => self::Value,
        'path'      => self::Path,
        'directory' => self::Directory,
        'file'      => self::File,
        'setting'   => self::Setting,
    ];

    /**
     * Nested scalar bag: scalars, `null`, `[]`, arrays of the same, or a {@see \UnitEnum}.
     *
     * Array nesting may not exceed 5 levels.
     */
    case Value;

    /**
     * Non-empty string; no file/directory distinction.
     */
    case Path;

    /**
     * Path whose last segment has no file extension.
     *
     * Trailing {@see \DIR_SEP} is a directory. `.` / `..` / `/` count.
     */
    case Directory;

    /**
     * Path whose last segment ends with a file extension.
     *
     * Trailing {@see \DIR_SEP} is not a file. Leading-dot names (`.env`) count.
     */
    case File;

    /**
     * Scalar or {@see \UnitEnum}; not `null`, not arrays, not other objects.
     */
    case Setting;

    /**
     * @param string|\Northrook\Parameter\Type $value
     *
     * @return \Northrook\Parameter\Type
     */
    public static function from(
        string|self $value,
    ): Type {
        if (\is_string($value)) {
            if (\substr_count($value, '::') === 1) {
                [$resolve, $type] = \explode('::', $value, 2);
                if (\ltrim($resolve, '\\') === __CLASS__) {
                    $resolve = \strtolower($type);
                }
            } else {
                $resolve = \strtolower($value);
            }

            if (\array_key_exists($resolve, self::CASES)) {
                return self::CASES[$resolve];
            }
        }

        if ($value instanceof self) {
            return $value;
        }

        throw new InvalidArgumentException(
            message: 'Unable to resolve ' . Type::class . ' from value.',
            context: ['value' => $value],
        );
    }
    /**
     * Whether `$value` matches this case's shape.
     *
     * @throws \Northrook\RuntimeException When {@see Type::Value} array nesting exceeds 5 levels.
     *
     * @phpstan-assert-if-true ParameterValue $value
     */
    public function validate(
        mixed $value,
    ): bool {
        return match ($this) {
            Type::Value     => $this->value($value),
            Type::Path      => $this->path($value),
            Type::Directory => $this->directory($value),
            Type::File      => $this->file($value),
            Type::Setting   => $this->setting($value),
        };
    }

    /**
     * Accepts scalars, `null`, `[]`, a {@see \UnitEnum}, or arrays of the same.
     *
     * Other objects fail. Nested arrays increment `$depth`; `$depth > 5` throws.
     *
     * @throws \Northrook\RuntimeException
     */
    private function value(
        mixed $value,
        int   $depth = 0,
    ): bool {
        if ($depth > 5) {
            throw new RuntimeException(
                message: 'Maximum recursion depth exceeded.',
                context: [
                    'type'  => $this,
                    'depth' => $depth,
                    'value' => $value,
                ],
            );
        }

        if (\is_scalar($value) || $value === null) {
            return true;
        }

        if (\is_array($value)) {
            return \array_all($value, fn($item) => $this->value($item, $depth + 1));
        }

        return $value instanceof \UnitEnum;
    }

    /**
     * Non-empty string.
     *
     * @phpstan-assert-if-true non-empty-string $value
     */
    private function path(
        mixed $value,
    ): bool {
        return \is_string($value) && $value !== '';
    }

    /**
     * Non-empty string whose last segment has no file extension.
     *
     * Trailing {@see \DIR_SEP} is a directory. `.` / `..` / `/` count.
     *
     * @phpstan-assert-if-true non-empty-string $value
     */
    private function directory(
        mixed $value,
    ): bool {
        if (! $this->path($value)) {
            return false;
        }

        return $this->fileExtension($value) === '';
    }

    /**
     * Non-empty string whose last segment ends with a file extension.
     *
     * Trailing {@see \DIR_SEP} is not a file.
     *
     * @phpstan-assert-if-true non-empty-string $value
     */
    private function file(
        mixed $value,
    ): bool {
        if (! $this->path($value)) {
            return false;
        }

        return $this->fileExtension($value) !== '';
    }

    /**
     * Suffix after the last `.` in the final path segment, or `''`.
     *
     * Leading-dot names (`.env`) count. `.` / `..` / trailing-dot / trailing
     * {@see \DIR_SEP} do not.
     *
     * @param non-empty-string $path
     *
     * @return string Extension without the leading `.`, or `''` when there is none.
     */
    private function fileExtension(
        string $path,
    ): string {
        if (\str_ends_with($path, \DIR_SEP)) {
            return '';
        }

        $slash = \strrpos($path, \DIR_SEP);
        $base  = $slash === false ? $path : \substr($path, $slash + 1);

        if ($base === '' || $base === '.' || $base === '..') {
            return '';
        }

        $dot = \strrpos($base, '.');

        if ($dot === false || $dot === ( \strlen($base) - 1 )) {
            return '';
        }

        return \substr($base, $dot + 1);
    }

    /**
     * Scalar or {@see \UnitEnum}.
     *
     * Rejects `null`, arrays, and other objects.
     *
     * @phpstan-assert-if-true bool|float|int|string|\UnitEnum $value
     */
    private function setting(
        mixed $value,
    ): bool {
        return \is_scalar($value) || $value instanceof \UnitEnum;
    }
}

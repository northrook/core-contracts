<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\InvalidArgumentException;
use Northrook\RuntimeException;

/**
 * Typed string values that denote a reference to something, with no guarantee it exists.
 *
 * Common use-cases include: paths to files and directories, URLs, etc.
 *
 * @property-read non-empty-string $value A validated, normalized, non-empty string
 */
interface Reference extends \Stringable
{
    /**
     * Canonical string form of `$value` for this reference type.
     *
     * Used to enforce alignment between instances, comparisons, and storage.
     *
     * @return non-empty-string
     *
     * @throws InvalidArgumentException When `$value` is malformed for this type
     */
    public static function normalize(
        string|\Stringable $value,
    ): string;

    /**
     * Whether `$value` is acceptable for this reference type.
     *
     * If {@see self::isValid()} is `true`, {@see self::normalize()} will not throw.
     *
     * @phpstan-assert-if-true non-empty-string|\Stringable $value
     */
    public static function isValid(
        string|\Stringable $value,
    ): bool;

    /**
     * Build a reference instance from `$value`, or `null` when invalid.
     *
     * When `$throw` is true, invalid input or resolver failures will throw exceptions.
     *
     * @return ($throw is true ? static : null|static)
     *
     * @throws InvalidArgumentException When `$throw` is true and `$value` is invalid
     * @throws RuntimeException         When `$throw` is true and a resolver fails
     */
    public static function from(
        mixed $value,
        bool  $throw = false,
    ): null|static;

    /**
     * Same string as {@see $value}.
     *
     * @return non-empty-string
     */
    public function __toString(): string;
}

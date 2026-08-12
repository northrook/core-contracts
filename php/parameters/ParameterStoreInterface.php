<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Mutable store of named parameters with an optional soft lock.
 *
 * - {@see $immutable} allows for a togglable soft-lock at compile-time.
 *
 * @phpstan-import-type ParameterValue from Parameter
 */
interface ParameterStoreInterface extends ParameterMapInterface
{
    /**
     * Soft lock flag.
     *
     * Critical: true means mutations must throw while `true`.
     */
    public bool $immutable { get; }

    /**
     * @param array<non-empty-string, ParameterValue|Parameter>  $parameters
     */
    public function assign(
        array $parameters,
        bool  $replace = false,
    ): void;

    /**
     * Will not override existing parameters.
     *
     * @param non-empty-string                     $key
     * @param ParameterValue|Parameter             $value
     * @param null|string|Value\Secret             $secret  type string or policy
     */
    public function add(
        string                   $key,
        mixed                    $value,
        null|string|Value\Secret $secret = null,
    ): self;

    /**
     * Replaces existing parameters.
     *
     * - `$tag` is a freeform non-empty hint: `path`, `Fully\Qualified\Classname`, etc
     *
     * @param non-empty-string                     $key
     * @param ParameterValue|Parameter             $value
     * @param null|string|Value\Secret             $secret  type string or policy
     * @param null|non-empty-string                $tag
     */
    public function set(
        string                   $key,
        mixed                    $value,
        null|string|Value\Secret $secret = null,
        null|string              $tag = null,
    ): self;

    /**
     * @param non-empty-string  ...$key
     */
    public function remove(
        string ...$key,
    ): self;

    /**
     * @throws RuntimeException if it cannot be cleared
     */
    public function clear(): void;
}

<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Mutable store of named parameters with an optional soft lock.
 *
 * - {@see $immutable} allows for a togglable soft-lock at compile-time.
 *
 *  @phpstan-import-type ParameterValue from \Northrook\Parameter
 *  @phpstan-type SecretArgument null|"sensitive"|"credential"|\Northrook\Container\Secret|\Northrook\Parameter\Secret
 */
interface ParameterStoreInterface
{
    /**
     * Soft lock flag.
     *
     * Critical: true means mutations must throw while `true`.
     */
    public bool $immutable { get; }

    /**
     * @param non-empty-string  $key
     */
    public function has(
        string $key,
    ): bool;

    /**
     * @param array<non-empty-string, ParameterValue|ParameterReference>  $parameters
     */
    public function assign(
        array $parameters,
        bool  $replace = false,
    ): void;

    /**
     * Will not override existing parameters.
     *
     * @param non-empty-string                   $key
     * @param ParameterValue|ParameterReference  $value
     * @param \Northrook\Parameter\Secret        $secret  tier string, enum, or `#[Secret]` attribute
     */
    public function add(
        string                                        $key,
        mixed                                         $value,
        null|string|Container\Secret|Parameter\Secret $secret = null,
    ): self;

    /**
     * Replaces existing parameters.
     *
     * - `$tag` is a freeform non-empty hint: `path`, `Fully\Qualified\Classname`, etc
     *
     * @param non-empty-string                   $key
     * @param ParameterValue|ParameterReference  $value
     * @param \Northrook\Parameter\Secret        $secret  tier string, enum, or `#[Secret]` attribute
     * @param null|non-empty-string              $tag
     */
    public function set(
        string                                        $key,
        mixed                                         $value,
        null|string|Container\Secret|Parameter\Secret $secret = null,
        null|string                                   $tag = null,
    ): self;

    /**
     * @param non-empty-string  $key
     * @param bool              $create  when true, insert a virgin {@see ParameterReference} (`Value::Unset`) if missing
     *
     * @return \Northrook\ParameterReference
     *
     * @throws \Northrook\UndefinedEntryException when `$key` does not exist and `$create` is false
     */
    public function get(
        string $key,
        bool   $create = false,
    ): ParameterReference;

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

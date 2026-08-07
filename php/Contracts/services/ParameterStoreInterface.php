<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Mutable store of named parameters with an optional soft lock.
 *
 * - {@see $immutable} allows for a togglable soft-lock at compile-time.
 * - `$factory` is compile-time only, and should not be used at runtime.
 *
 * @phpstan-import-type Type from Secret
 * @phpstan-type ParameterValue array<array-key, mixed>|bool|float|int|string|\UnitEnum|null
 * @phpstan-type ParameterFactory callable(ParameterValue): mixed
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
     * @param non-empty-string  $key
     *
     * @return ParameterInterface
     */
    public function getParameter(
        string $key,
    ): ParameterInterface;

    /**
     * @param array<non-empty-string, ParameterValue|ParameterInterface>  $parameters
     */
    public function assign(
        array $parameters,
        bool  $replace = false,
    ): void;

    /**
     * Will not override existing parameters.
     *
     * @param non-empty-string                   $key
     * @param ParameterValue|ParameterInterface  $value
     * @param null|Type                          $secret
     */
    public function add(
        string      $key,
        mixed       $value,
        null|string $secret = null,
    ): self;

    /**
     * Replaces existing parameters.
     *
     * - `$tag` is a freeform non-empty hint: `path`, `Fully\Qualified\Classname`, etc
     * - `$factory` is invoked at compile-time only
     *
     * @param non-empty-string                   $key
     * @param ParameterValue|ParameterInterface  $value
     * @param null|Type                          $secret
     * @param null|non-empty-string              $tag
     * @param null|ParameterFactory              $factory
     */
    public function set(
        string        $key,
        mixed         $value,
        null|string   $secret = null,
        null|string   $tag = null,
        null|callable $factory = null,
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

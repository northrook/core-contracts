<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * @property-read Key $key
 * @property-read Value $value
 * @property-read null|Type $secret
 * @property-read null|non-empty-string $tag
 * @property-read null|Factory $factory Compile-time only; not cache-persisted
 *
 * @phpstan-import-type Type from Secret
 * @phpstan-type Key non-empty-lowercase-string
 * @phpstan-type Value array<array-key, mixed>|bool|float|int|string|\UnitEnum|null
 * @phpstan-type Factory callable(Value): mixed
 */
interface ParameterInterface
{
    /**
     * @param Key           $key
     * @param Value         $value
     * @param null|string   $secret
     * @param null|string   $tag
     * @param null|Factory  $factory
     *
     * @return ParameterInterface
     */
    public static function from(
        string      $key,
        mixed       $value,
        null|string $secret = null,
        null|string $tag = null,
        mixed       $factory = null,
    ): self;
}

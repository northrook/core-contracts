<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * @property-read \Northrook\Contracts\Parameter\Type     $type
 * @property-read non-empty-lowercase-string              $key
 * @property-read ParameterValue                          $value
 * @property-read null|\Northrook\Contracts\Value\Secret  $secret
 *
 * @phpstan-type ParameterValue bool|float|int|string|\UnitEnum|null|array<array-key, mixed>
 */
interface ParameterInterface
{
    public function isTagged(
        string ...$value,
    ): bool;
}

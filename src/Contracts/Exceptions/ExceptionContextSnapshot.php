<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

/**
 * Frozen associative context for exception payloads.
 *
 * Assign {@see $context} in the using class constructor via {@see \Northrook\Contracts\Snapshot::context()}.
 *
 * @phpstan-require-extends \Exception
 */
trait ExceptionContextSnapshot
{
    /** @var array<array-key, mixed> */
    public readonly array $context;
}

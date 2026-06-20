<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Attributes;

use Attribute;

/**
 * Typed sentinel constants for required parameter declarations.
 *
 * Use these as attribute argument defaults so the container can infer the
 * expected type when a parameter is marked required but no value is supplied.
 *
 * @example #[Parameter(default: Requires::STRING)]
 */
#[Attribute]
final class Requires
{
    public const null NULL = null;

    public const bool BOOL = false;

    public const true TRUE = true;

    public const false FALSE = false;

    public const string STRING = '';

    public const int INT = 0;

    public const float FLOAT = 0;

    public const array ARRAY = [];

    public const array ARG = [];

    public const array ARGS = [[]];
}

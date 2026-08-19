<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Context;

final class SecretMask
{
    public static function sensitive(
        mixed $value,
    ): string {
        $label = Context::isDebug()
            ? \debug_value_type($value)
            : \gettype($value);

        return "[secret::{$label}]";
    }
}

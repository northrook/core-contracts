<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

/**
 * Intentional invalid call sites kept out of PHPStan analysis.
 */
final class InvalidRegexpCalls
{
    public static function unclosedNamedGroup(): void
    {
        @\preg_match(
                /** @lang none */
                '/(?P<unclosed/', 'subject');
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use function Northrook\Contracts\Internal\_match_charset;
use function Northrook\Contracts\is_valid_key;

final class InvalidValidationCalls
{
    public static function matchCharsetWithEmptyCharset(): void
    {
        _match_charset('a', '');
    }

    public static function validKeyWithEmptyCharset(): void
    {
        is_valid_key('abc', charset: '');
    }

    public static function validKeyWithMultiByteSeparator(): void
    {
        is_valid_key('a.b', separator: '..');
    }

    public static function validKeyWithSeparatorInCharset(): void
    {
        is_valid_key('a-b', separator: '-', charset: 'abc-');
    }
}

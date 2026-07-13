<?php

declare(strict_types=1);

namespace Northrook;

enum AppEnvironment: string
{
    case Production = 'production';

    case Development = 'development';

    case Testing = 'testing';

    case Staging = 'staging';

    case Failsafe = 'failsafe';

    public static function parse(
        string $value,
    ): self {
        return match (\strtolower($value)) {
            'prod', 'production' => self::Production,
            'dev', 'development' => self::Development,
            'test', 'testing'    => self::Testing,
            'staging'            => self::Staging,
            default              => self::Failsafe,
        };
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container;

use InvalidArgumentException;

final readonly class Priority
{
    public const null AUTO = null;
    public const int MAX  = 1_024;
    public const int MIN  = -1_024;

    private function __construct() {}

    /**
     * @param null|int $priority
     * @return null|int<self::MIN, self::MAX>
     */
    public static function resolve(
        null|int $priority,
    ): null|int {
        if ($priority === Priority::AUTO) {
            return null;
        }

        if ($priority < Priority::MIN || $priority > Priority::MAX) {
            $min = Priority::MIN;
            $max = Priority::MAX;
            throw new InvalidArgumentException(
                "Invalid priority: `{$priority}`, it must be between `{$min}` and `{$max}`.",
            );
        }

        return $priority;
    }
}

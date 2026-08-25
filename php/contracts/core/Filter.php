<?php

declare(strict_types=1);

namespace Northrook;

enum Filter
{
    public const array CASES = [
        'and' => self::AND,
        'or'  => self::OR,
        'not' => self::NOT,
    ];

    case AND;

    case OR;

    case NOT;

    /**
     * @param string|array<array-key,mixed>|\Northrook\Filter &$from
     * @param \Northrook\Filter $default
     * @param bool $unset
     * @return \Northrook\Filter
     */
    public static function resolve(
        string|array|Filter &$from,
        Filter               $default = Filter::AND,
        bool                 $unset = false,
    ): Filter {
        if (empty($from)) {
            return $default;
        }

        if ($from instanceof Filter) {
            return $from;
        }

        if (\is_array($from)) {
            $resolve = \array_change_key_case($from, CASE_LOWER)['filter'] ?? null;

            if (\is_string($resolve)) {
                $resolve = \strtolower($resolve);

                if (\array_key_exists($resolve, self::CASES)) {
                    $resolve = self::CASES[$resolve];
                }
            }

            if ($resolve instanceof Filter) {
                if ($unset) {
                    unset($from['filter']);
                }

                return $resolve;
            }
        }
        else {
            $resolve = \strtolower($from);
        }

        return match ($resolve) {
            'and'   => Filter::AND,
            'or'    => Filter::OR,
            'not'   => Filter::NOT,
            default => $default,
        };
    }
}

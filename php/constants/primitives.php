<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/** Do not cache at all */
const CACHE_DISABLED = -2;
/** In-memory runtime cache */
const CACHE_EPHEMERAL = -1;
/** Follow Adapter rules */
const CACHE_AUTO = null;
/** No expiration time */
const CACHE_FOREVER = 0;

// TODO : move to Duration
const
    DURATION_MINUTE = 60,
    DURATION_HOUR_1 = 3_600,
    DURATION_HOUR_4 = 14_400,
    DURATION_HOUR_8 = 28_800,
    DURATION_HOUR_12 = 43_200,
    DURATION_DAY = 86_400,
    DURATION_WEEK = 604_800,
    DURATION_MONTH = 2_592_000,
    DURATION_YEAR = 31_536_000
;

/**
 * Use with `strtr` to efficiently remove all whitespace from a string.
 * ```
 * return \strtr( $string, \Support\REMOVE_WHITESPACE );
 * ```
 */
const REMOVE_WHITESPACE = [
    ' '    => '',
    "\t"   => '',
    "\n"   => '',
    "\r"   => '',
    "\0"   => '',
    "\x0B" => '',
];

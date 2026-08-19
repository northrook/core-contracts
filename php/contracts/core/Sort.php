<?php

declare(strict_types=1);

namespace Northrook;

enum Sort
{
    /** Locale-aware string comparison; A→Z or Z→A depending on {@see Order}. */
    case Alpha;

    /**
     * Alphanumeric natural order.
     *
     * Treats embedded digit runs as numbers: `file2` before `file10`.
     * Equivalent to PHP's `natsort` / `SORT_NATURAL`.
     */
    case Natural;

    /** Coerce values to numbers before comparing. */
    case Numeric;

    /** Compare by string length or collection count. */
    case Length;

    /** Compare by file or payload size in bytes. */
    case Size;

    /** Compare by date/time value. */
    case Date;

    /**
     * Treat values as booleans; `false` sorts before `true` with {@see Order::ASC}.
     *
     * Combine with {@see Order::DESC} to put truthy values first.
     */
    case Boolean;

    /**
     * Caller-supplied comparator; signals that no built-in criterion applies.
     *
     * Consumers should pair this with an explicit callable or comparator object.
     */
    case Custom;
}

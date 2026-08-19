<?php

declare(strict_types=1);

namespace Northrook;

enum Order
{
    /** Ascending — lowest/earliest/A first. */
    case ASC;

    /** Descending — highest/latest/Z first. */
    case DESC;

    /**
     * No explicit ordering preference.
     *
     * Allows locale, client preference, or upstream ordering to take effect.
     * Use with an explicit override to suppress default sort behaviour.
     */
    case None;

    /** Randomise the result set. */
    case Random;
}

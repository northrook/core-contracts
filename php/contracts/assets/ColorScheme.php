<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\RuntimeContext;

/**
 * Polarity of a {@see ColorPalette}.
 */
enum ColorScheme: string implements RuntimeContext
{
    /**
     * Light canvas; dark foregrounds and elevated surfaces go darker.
     */
    case Light = 'light';

    /**
     * Dark canvas; light foregrounds and elevated surfaces go lighter.
     */
    case Dark = 'dark';
}

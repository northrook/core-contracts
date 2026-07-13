<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Polarity of a {@see ColorPalette}.
 */
enum ColorScheme: string
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

<?php

declare(strict_types=1);

namespace Northrook\Assets;

/**
 * Declares the form of an {@see \Northrook\AssetInterface} payload.
 *
 * The accompanying `$value` is interpreted according to this origin.
 *
 * Emit, fetch, and CDN→local resolution are Handler/Manager concerns.
 *
 * @used-by \Northrook\AssetInterface
 */
enum AssetOrigin: string
{
    /**
     * Filesystem path.
     *
     * `$value` is a local path string.
     */
    case Path = 'path';

    /**
     * Absolute or CDN URL.
     *
     * `$value` is a URL string.
     */
    case Url = 'url';

    /**
     * Inline payload.
     *
     * `$value` is the raw data string (SVG markup, CSS, JS, binary).
     */
    case Data = 'data';
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Assets;

use Northrook\Contracts\Interfaces\AssetInterface;

/**
 * Declares how an {@see AssetInterface} is intended to be used.
 *
 * @used-by AssetInterface, AssetCollection
 */
enum AssetType: string
{
    /**
     * Stylesheet; CSS
     *
     * Typical emission: `<link rel="stylesheet">`, `<style>`.
     */
    case Style = 'style';

    /**
     * Executable script; JS / MJS module sources
     *
     * Typical emission: `<script>`, `<script type="module">`.
     *
     * Prefer {@see self::Worker} when the asset is registered as a worker entry.
     */
    case Script = 'script';

    /**
     * Web font face; woff, woff2, ttf, otf and similar.
     *
     * Typical emission: `@font-face`, font preload
     */
    case Font = 'font';

    /**
     * Raster image resource; png, jpg, webp, gif, avif, …
     *
     * Multi-format visual slot for bitmaps.
     *
     * Use {@see self::Vector} when a vector is used as an image.
     */
    case Image = 'image';

    /**
     * Vector used as an image; decorative art, backgrounds, full-bleed visuals
     *
     * Same visual role as {@see self::Image}, but the payload is vector.
     */
    case Vector = 'vector';

    /**
     * Raw SVG markup data; no implied presentation
     *
     * Intended for code consumers that expect SVG (icon managers, inline visualisations).
     */
    case Svg = 'svg';

    /**
     * Icons; favicon.ico, touch icons, and similar binary icons
     *
     * Typical emission: `<link rel="icon">` and related tags.
     */
    case Icon = 'icon';

    /**
     * Audio media mp3, wav, ogg, …
     *
     * Typical emission: `<audio>`, preload as audio.
     */
    case Audio = 'audio';

    /**
     * Video media; mp4, webm, mov, …
     *
     * Typical emission: `<video>`, preload as video
     */
    case Video = 'video';

    /**
     * Runtime binary; e.g. WebAssembly modules.
     */
    case Binary = 'binary';

    /**
     * Web Application Manifest; e.g. `site.webmanifest`.
     *
     * Typical emission: `<link rel="manifest">`.
     */
    case Manifest = 'manifest';

    /**
     * Worker entry; service worker or dedicated/shared worker script.
     *
     * Same language family as {@see self::Script}, loaded as a worker rather than a page script.
     */
    case Worker = 'worker';

    /**
     * JSON data payload.
     *
     * Structured data for consumers / API clients.
     */
    case JSON = 'json';

    /**
     * XML data payload.
     *
     * Structured data for consumers / API clients.
     */
    case XML = 'xml';

    /**
     * YAML data payload.
     *
     * Structured data for consumers / API clients.
     */
    case YAML = 'yaml';
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Trust signal for how packages or services handle asset rendering.
 */
enum RenderStrategy
{
    /**
     * Markup only.
     *
     * Assets should be handled by the application.
     */
    case INTEGRATED;

    /**
     * Inline styles.
     *
     * Minimal style/script emission when unavoidable.
     */
    case INLINE;

    /**
     * Standalone assets.
     *
     * Provide all required assets.
     */
    case STANDALONE;

    /**
     * Prefer {@see self::INTEGRATED} when the host already covers assets, otherwise {@see self::STANDALONE}.
     */
    case AUTO;
}

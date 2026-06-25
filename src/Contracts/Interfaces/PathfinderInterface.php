<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Stringable;

/**
 * Resolves filesystem paths and public URLs relative to the application root.
 */
interface PathfinderInterface
{
    /**
     * @param string|Stringable      $path
     * @param null|string|Stringable $relativeTo
     * @param bool                   $nullable
     *
     * @return ($nullable is true ? null|string : non-empty-string)
     */
    public function getPath(
        string|Stringable $path,
        null|string|Stringable $relativeTo = null,
        bool $nullable = false,
    ): null|string;

    /**
     * @param string|Stringable      $path
     * @param null|string|Stringable $relativeTo
     * @param bool                   $nullable
     *
     * @return ($nullable is true ? null|string : non-empty-string)
     */
    public function getUrl(
        string|Stringable $path,
        null|string|Stringable $relativeTo = null,
        bool $nullable = false,
    ): null|string;
}

<?php

namespace Core\Contracts;

use Stringable;

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
        string|Stringable      $path,
        null|string|Stringable $relativeTo = null,
        bool                   $nullable = false,
    ) : ?string;

    /**
     * @param string|Stringable      $path
     * @param null|string|Stringable $relativeTo
     * @param bool                   $nullable
     *
     * @return ($nullable is true ? null|string : non-empty-string)
     */
    public function getUrl(
        string|Stringable      $path,
        null|string|Stringable $relativeTo = null,
        bool                   $nullable = false,
    ) : ?string;
}

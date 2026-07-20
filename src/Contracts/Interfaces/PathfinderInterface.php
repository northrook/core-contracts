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
     * Resolves `$reference` to a filesystem {@see PathInterface}.
     *
     * Relative references without `{parameter.key}` braces are resolved from the project root.
     *
     * @param string|Stringable $reference `{key}`, `{key}/suffix`, or path
     *
     * @throws \Northrook\Contracts\Exceptions\InvalidArgumentException when {@see static::getUrl()} should have been called
     */
    public function getPath(
        string|Stringable $reference,
    ): null|PathInterface;

    /**
     * Resolves `$reference` to a public {@see UrlInterface}.
     *
     * Relative references without `{parameter.key}` braces are resolved from `url.base`.
     *
     * @param string|Stringable $reference `{key}`, `{key}/suffix`, or URL/path
     *
     * @throws \Northrook\Contracts\Exceptions\InvalidArgumentException when {@see static::getPath()} should have been called
     */
    public function getUrl(
        string|Stringable $reference,
    ): null|UrlInterface;
}

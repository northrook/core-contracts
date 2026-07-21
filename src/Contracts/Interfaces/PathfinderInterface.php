<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

/**
 * Resolves configured parameter references into filesystem paths and public URLs.
 *
 * Call-site forms: `{key}`, `{key}/suffix`, or a bare/absolute/URI string.
 * Bare references (no leading `{key}`) are passed through unchanged, then
 * type-checked by the method — there is no implicit join onto a project root
 * or `url.base`.
 */
interface PathfinderInterface
{
    /**
     * Resolves `$reference` to a filesystem {@see PathInterface}.
     *
     * @param string|\Stringable $reference `{key}`, `{key}/suffix`, `path/to/location`
     *
     * @return null|PathInterface `null` when the reference cannot be resolved
     *
     * @throws \Northrook\Contracts\Exceptions\InvalidArgumentException when the resolved value is a URL shape
     * @throws \Northrook\Contracts\Exceptions\RuntimeException when the `$reference` value is malformed
     */
    public function getPath(
        string|\Stringable $reference,
    ): null|PathInterface;

    /**
     * Resolves `$reference` to a public {@see UrlInterface}.
     *
     * @param string|\Stringable $reference `{key}`, `{key}/suffix`, `scheme://host/path`
     *
     * @return null|UrlInterface `null` when the reference cannot be resolved
     *
     * @throws \Northrook\Contracts\Exceptions\InvalidArgumentException when the resolved value is filesystem path
     *  @throws \Northrook\Contracts\Exceptions\RuntimeException when the `$reference` value is malformed
     *
     */
    public function getUrl(
        string|\Stringable $reference,
    ): null|UrlInterface;
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Resolves configured parameter references into filesystem paths and public URIs.
 *
 * Call-site forms: `{key}`, `{key}/suffix`, or a bare/absolute/URI string.
 */
interface PathfinderInterface
{
    /**
     * Resolves `$reference` to a filesystem {@see Path}.
     *
     * @param string|\Stringable $reference `{key}`, `{key}/suffix`, `path/to/location`
     *
     * @return null|Path `null` when the reference cannot be resolved
     *
     * @throws InvalidArgumentException when the resolved value is a URI shape
     * @throws RuntimeException when the `$reference` value is malformed
     */
    public function getPath(
        string|\Stringable $reference,
    ): null|Path;

    /**
     * Resolves `$reference` to a public {@see Uri}.
     *
     * @param string|\Stringable $reference `{key}`, `{key}/suffix`, `scheme://host/path`
     *
     * @return null|Uri `null` when the reference cannot be resolved
     *
     * @throws InvalidArgumentException when the resolved value is a filesystem path
     * @throws RuntimeException when the `$reference` value is malformed
     */
    public function getUrl(
        string|\Stringable $reference,
    ): null|Uri;
}

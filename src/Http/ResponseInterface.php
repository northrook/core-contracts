<?php

namespace Core\Contracts\Http;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @property-read  ResponseHeaderBag $headers Response HTTP headers
 */
interface ResponseInterface
{
    /**
     * Marks the response as "public".
     *
     * It makes the response eligible for serving other clients.
     *
     * @return $this
     *
     * @final
     */
    public function setPublic() : static;

    /**
     * Marks the response as "private".
     *
     * It makes the response ineligible for serving other clients.
     *
     * @return $this
     *
     * @final
     */
    public function setPrivate() : static;

    /**
     * Sets the response content.
     *
     * @param ?string $content
     *
     * @return $this
     */
    public function setContent( ?string $content ) : static;

    /**
     * Gets the current response content.
     */
    public function getContent() : string|false;

    /**
     * Retrieves the status code for the current web response.
     *
     * @final
     */
    public function getStatusCode() : int;

    /**
     * Returns true if the response may safely be kept in a shared (surrogate) cache.
     *
     * Responses marked "private" with an explicit Cache-Control directive are
     * considered uncacheable.
     *
     * Responses with neither a freshness lifetime (Expires, max-age) nor cache
     * validator (Last-Modified, ETag) are considered uncacheable because there is
     * no way to tell when or how to remove them from the cache.
     *
     * Note that RFC 7231 and RFC 7234 possibly allow for a more permissive implementation,
     * for example, "status codes that are defined as cacheable by default [...]
     * can be reused by a cache with heuristic expiration unless otherwise indicated"
     * (https://tools.ietf.org/html/rfc7231#section-6.1)
     *
     * @final
     */
    public function isCacheable() : bool;

    /**
     * Returns true if the response is "fresh".
     *
     * Fresh responses may be served from the cache without any interaction with the
     * origin. A response is considered fresh when it includes a Cache-Control/max-age
     * indicator or Expires header, and the calculated age is less than the freshness lifetime.
     *
     * @final
     */
    public function isFresh() : bool;

    /**
     * Returns true if the response includes headers that can be used to validate
     * the response with the origin server using a conditional GET request.
     *
     * @final
     */
    public function isValidateable() : bool;
}

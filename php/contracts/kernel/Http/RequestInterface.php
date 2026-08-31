<?php

declare(strict_types=1);

namespace Northrook\Http;

use Northrook\Http\Request\Method;
use Northrook\Http\Request\Scheme;
use Symfony\Component\HttpFoundation\{FileBag, HeaderBag, InputBag, ParameterBag, ServerBag};

/**
 * HTTP request abstraction for handling a {@see \Symfony\Component\HttpFoundation\Request}.
 */
interface RequestInterface
{
    /**
     * Custom parameters.
     */
    public ParameterBag $parameterBag { get; }

    /**
     * Request body parameters ($_POST).
     *
     * @var InputBag<string>
     */
    public InputBag $request { get; }

    /**
     * Query string parameters ($_GET).
     *
     * @var InputBag<string>
     */
    public InputBag $query { get; }

    /**
     * Headers (taken from the $_SERVER).
     */
    public HeaderBag $headers { get; }

    /**
     * Cookies ($_COOKIE).
     *
     * @var InputBag<string>
     */
    public InputBag $cookies { get; }

    /**
     * Server and execution environment parameters ($_SERVER).
     */
    public ServerBag $server { get; }

    /**
     * Uploaded files ($_FILES).
     */
    public FileBag $files { get; }

    /**
     * The request method.
     */
    public Method $method { get; }

    /**
     * Gets the request's scheme.
     */
    public Scheme $scheme { get; }

    /**
     * Returns the HTTP host being requested.
     *
     * The port name will be appended to the host if it's non-standard.
     */
    public string $httpHost { get; }

    /**
     * Gets the scheme and HTTP host.
     *
     * If the URL was called with basic authentication, the user
     * and the password are not added to the generated string.
     */
    public string $schemeAndHttpHost { get; }

    /**
     * Returns the requested URI (path and query string).
     *
     * @return string raw, unencoded URI
     */
    public string $requestUri { get; }

    /**
     * Get the locale.
     */
    public string $locale { get; }

    /**
     * Creates a new request with values from PHP's super globals.
     */
    public static function createFromGlobals(): static;

    /**
     * Gets a `parameter` value from any bag.
     *
     * Order of precedence: `PATH`, `GET`, `POST`.
     *
     * @internal access {@see ParameterBag} properties directly
     *
     * @param string      $key
     * @param null|mixed  $default
     */
    public function get(
        string $key,
        mixed  $default = null,
    ): mixed;

    /**
     * Whether the request contains a Session object.
     */
    public function hasSession(): bool;

    /**
     * Checks whether the request is secure or not.
     *
     * This method can read the client protocol from the "X-Forwarded-Proto" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Proto" header must contain the protocol: "https" or "http".
     */
    public function isSecure(): bool;

    /**
     * Indicates whether this request originated from a trusted proxy.
     *
     * This can be useful to determine whether to trust the contents of a proxy-specific header.
     */
    public function isFromTrustedProxy(): bool;

    /**
     * Returns true if the request is an XMLHttpRequest.
     *
     * It works if your JavaScript library sets an X-Requested-With HTTP header.
     * It is known to work with common JavaScript frameworks:
     *
     * @see https://wikipedia.org/wiki/List_of_Ajax_frameworks#JavaScript
     */
    public function isXmlHttpRequest(): bool;

    /**
     * Checks whether the request has a `no-cache` header set.
     *
     * @return bool
     */
    public function isNoCache(): bool;

    /**
     * Checks whether the client browser prefers safe content according to RFC8674.
     *
     * @see https://tools.ietf.org/html/rfc8674 RFC8674
     */
    public function preferSafeContent(): bool;
}

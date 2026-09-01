<?php

declare(strict_types=1);

namespace Northrook\Reference;

use Northrook\Contracts\Reference;
use Northrook\CurlInterface;
use Northrook\Filesystem\File;
use Northrook\Filesystem\Path;
use Northrook\InvalidArgumentException;
use Northrook\ReferenceTrait;
use Northrook\RuntimeException;
use Uri\InvalidUriException;
use Uri\UriComparisonMode;

/**
 *
 * RFC 3986 URI {@see Reference} — plumbing base.
 *
 * Covers any scheme (`https`, `mailto`, `file`, custom, …) and URI-references.
 * A validated denoting string only — no guarantee the target exists or is reachable.
 *
 * Not a subclass of {@see \Uri\Rfc3986\Uri} (final); that type is an intended
 * internal parser. Prefer {@see Url} or {@see Href} at call sites; use this type
 * for opaque schemes, relatives, resolution bases, and internal plumbing.
 *
 * Relative resolution uses the constructor `$base` or {@see resolve()} — not
 * {@see from()}, which only accepts/builds from string shape.
 *
 * Network fetch helpers live on {@see Url}. HTML link targets use {@see Href}.
 * Filesystem locations use {@see Path} / {@see File}, not this type.
 *
 *
 */
class Uri implements Reference
{
    use ReferenceTrait;

    /**
     * Canonical URI string after {@see normalize()}.
     *
     * @var non-empty-string
     */
    public string $value;

    private null|\Uri\Rfc3986\Uri $parsed = null;

    /**
     * Builds from `$uri`, optionally resolving against `$base`.
     *
     * @param string|\Stringable $uri  Absolute URI or URI-reference
     * @param null|self          $base Base for relative resolution; ignored when `$uri` is absolute
     *
     * @throws InvalidArgumentException When `$uri` is malformed for this type
     */
    public function __construct(
        string|\Stringable $uri,
        null|self          $base = null,
    ) {
        if ($base === null) {
            $this->value = static::normalize($uri);

            return;
        }

        $string = \string($uri);

        if ($string === '') {
            throw new InvalidArgumentException(
                message: 'URI cannot be empty.',
                context: [
                    'name'     => 'uri',
                    'expected' => 'non-empty URI',
                    'received' => $uri,
                ],
            );
        }

        try {
            $parsed = new \Uri\Rfc3986\Uri($string, $base->rfc());
        }
        catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'uri',
                    'expected' => 'valid RFC 3986 URI',
                    'received' => $uri,
                ],
                previous: $exception,
            );
        }

        $resolved = $parsed->toString();

        if ($resolved === '') {
            throw new InvalidArgumentException(
                message: 'URI normalized to an empty string.',
                context: [
                    'name'     => 'uri',
                    'expected' => 'non-empty URI',
                    'received' => $uri,
                ],
            );
        }

        // Re-enter {@see normalize()} so subclasses (e.g. {@see Url}) apply their rules.
        $this->value = static::normalize($resolved);
    }

    /**
     * Canonical URI string form (scheme casing, authority rules, etc.).
     *
     * Does not resolve relatives against a base — use the constructor or {@see resolve()}.
     *
     * {@inheritDoc}
     *
     * @return non-empty-string
     */
    public static function normalize(
        string|\Stringable $value,
    ): string {
        $string = \string($value);

        if ($string === '') {
            throw new InvalidArgumentException(
                message: 'URI cannot be empty.',
                context: [
                    'name'     => 'uri',
                    'expected' => 'non-empty URI',
                    'received' => $value,
                ],
            );
        }

        try {
            $normalized = new \Uri\Rfc3986\Uri($string)->toString();
        }
        catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'uri',
                    'expected' => 'valid RFC 3986 URI',
                    'received' => $value,
                ],
                previous: $exception,
            );
        }

        if ($normalized === '') {
            throw new InvalidArgumentException(
                message: 'URI normalized to an empty string.',
                context: [
                    'name'     => 'uri',
                    'expected' => 'non-empty URI',
                    'received' => $value,
                ],
            );
        }

        return $normalized;
    }

    protected function rfc(): \Uri\Rfc3986\Uri
    {
        if ($this->parsed !== null) {
            return $this->parsed;
        }

        try {
            return $this->parsed = new \Uri\Rfc3986\Uri($this->value);
        }
        catch (InvalidUriException $exception) {
            throw new RuntimeException(
                message : 'RFC 3986 URI parsing failed.',
                context : ['value' => $this->value],
                previous: $exception,
            );
        }
    }

    /**
     * @param \Uri\Rfc3986\Uri  $uri
     *
     * @return static
     */
    protected function withRfc(
        \Uri\Rfc3986\Uri $uri,
    ): static {
        return new static(uri: $uri->toString());
    }

    // -------------------------------------------------------------------------
    // Components
    // -------------------------------------------------------------------------

    /**
     * Scheme (e.g. `https`, `mailto`), or null when absent (URI-reference).
     */
    public function scheme(): null|string
    {
        return $this->rfc()->getScheme();
    }

    /**
     * Userinfo (`user` or `user:password`), or null when absent.
     */
    public function userInfo(): null|string
    {
        return $this->rfc()->getUserInfo();
    }

    /**
     * Host component, or null when absent (typical for relative references and `mailto:`).
     */
    public function host(): null|string
    {
        return $this->rfc()->getHost();
    }

    /**
     * Explicit port, or null when omitted / scheme-default.
     */
    public function port(): null|int
    {
        return $this->rfc()->getPort();
    }

    /**
     * Path component; may be empty (e.g. `https://example.com`).
     */
    public function path(): string
    {
        return $this->rfc()->getPath();
    }

    /**
     * Raw query string without the leading `?`, or null when absent.
     *
     * Prefer {@see queryParams()} when you need a parsed map.
     */
    public function query(): null|string
    {
        return $this->rfc()->getQuery();
    }

    /**
     * Parsed query parameters (duplicate keys become lists).
     *
     * @return array<string, mixed>
     */
    public function queryParams(): array
    {
        $query = $this->query();

        if ($query === null || $query === '') {
            return [];
        }

        $params = [];

        foreach (\explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = \explode('=', $pair, 2);
            $key   = \rawurldecode($parts[0]);
            $value = \array_key_exists(1, $parts)
                ? \rawurldecode($parts[1])
                : '';

            if (\array_key_exists($key, $params)) {
                if (! \is_array($params[$key])) {
                    $params[$key] = [$params[$key]];
                }

                $params[$key][] = $value;
            }
            else {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Fragment without the leading `#`, or null when absent.
     */
    public function fragment(): null|string
    {
        return $this->rfc()->getFragment();
    }

    // -------------------------------------------------------------------------
    // Predicates
    // -------------------------------------------------------------------------

    /**
     * Whether a scheme is present (absolute URI, not a bare URI-reference).
     */
    public function isAbsolute(): bool
    {
        return $this->scheme() !== null;
    }

    /**
     * Whether this is a URI-reference (no scheme).
     */
    public function isRelative(): bool
    {
        return $this->scheme() === null;
    }

    /**
     * Whether the scheme is `http` or `https`.
     */
    public function isHttp(): bool
    {
        $scheme = $this->scheme();

        return $scheme === 'http' || $scheme === 'https';
    }

    /**
     * Whether the scheme is `https`.
     */
    public function isSecure(): bool
    {
        return $this->scheme() === 'https';
    }

    /**
     * Whether the scheme is `mailto`.
     */
    public function isMailto(): bool
    {
        return $this->scheme() === 'mailto';
    }

    /**
     * Whether the scheme is `file`.
     */
    public function isFile(): bool
    {
        return $this->scheme() === 'file';
    }

    /**
     * Structural equality of the canonical URI string.
     *
     * @param bool $includeFragment When false, fragments are ignored in the comparison
     */
    public function equals(
        self $other,
        bool $includeFragment = false,
    ): bool {
        return $this->rfc()->equals(
            $other->rfc(),
            $includeFragment
                ? UriComparisonMode::IncludeFragment
                : UriComparisonMode::ExcludeFragment,
        );
    }

    // -------------------------------------------------------------------------
    // Withers (immutable)
    // -------------------------------------------------------------------------

    /**
     * Append a path segment. Returns a new instance.
     *
     * For query merges use {@see mergeQuery()}.
     *
     * @param string|\Stringable  $string
     *
     * @return static
     */
    public function append(
        string|\Stringable $string,
    ): static {
        $addon = \string($string);

        if ($addon === '') {
            return $this;
        }

        $path    = $this->path();
        $segment = \ltrim($addon, '/');

        if ($path === '' || $path === '/') {
            $next = '/' . $segment;
        }
        else {
            $next = \rtrim($path, '/') . '/' . $segment;
        }

        return $this->withPath($next);
    }

    /**
     * Replace or clear the scheme.
     *
     * Pass null to produce a URI-reference (no scheme).
     *
     * @param null|string  $scheme
     *
     * @return static
     */
    public function withScheme(
        null|string $scheme,
    ): static {
        try {
            return $this->withRfc($this->rfc()->withScheme($scheme));
        }
        catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'scheme',
                    'expected' => 'valid scheme',
                    'received' => $scheme,
                ],
                previous: $exception,
            );
        }
    }

    /**
     * Replace or clear userinfo.
     *
     * Pass null to remove credentials from the authority.
     *
     * @param null|string  $userInfo
     *
     * @return static
     */
    public function withUserInfo(
        null|string $userInfo,
    ): static {
        try {
            return $this->withRfc($this->rfc()->withUserInfo($userInfo));
        }
        catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'userInfo',
                    'expected' => 'valid userInfo',
                    'received' => $userInfo,
                ],
                previous: $exception,
            );
        }
    }

    /**
     * Replace or clear the host.
     *
     * Pass null to remove the host. Empty string is rejected.
     *
     * @param null|string  $host
     *
     * @return static
     */
    public function withHost(
        null|string $host,
    ): static {
        if ($host === '') {
            throw new InvalidArgumentException(
                message: 'Host cannot be an empty string.',
                context: [
                    'name'     => 'host',
                    'expected' => 'non-empty host or null',
                    'received' => $host,
                ],
            );
        }

        try {
            return $this->withRfc($this->rfc()->withHost($host));
        }
        catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'host',
                    'expected' => 'valid host',
                    'received' => $host,
                ],
                previous: $exception,
            );
        }
    }

    /**
     * Replace or clear the port.
     *
     * Pass null to omit an explicit port (scheme default applies at use sites).
     *
     * @param null|int  $port
     *
     * @return static
     */
    public function withPort(
        null|int $port,
    ): static {
        try {
            return $this->withRfc($this->rfc()->withPort($port));
        }
        catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'port',
                    'expected' => 'valid port',
                    'received' => $port,
                ],
                previous: $exception,
            );
        }
    }

    /**
     * Replace the path component.
     *
     * @param string  $path
     *
     * @return static
     */
    public function withPath(
        string $path,
    ): static {
        try {
            return $this->withRfc($this->rfc()->withPath($path));
        }
        catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'path',
                    'expected' => 'valid path',
                    'received' => $path,
                ],
                previous: $exception,
            );
        }
    }

    /**
     * Replace or clear the entire query.
     *
     * Array values must be null, scalar, or {@see \Stringable} (list items likewise).
     * Invalid values or an invalid resulting URI throw; this instance is unchanged.
     *
     * @param array<string, mixed>|string|null $query Parsed map, raw query (no `?`), or null to clear
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function withQuery(
        array|string|null $query,
    ): static {
        try {
            if ($query === null) {
                return $this->withRfc($this->rfc()->withQuery(null));
            }

            if (\is_string($query)) {
                $raw = \ltrim($query, '?');
                return $this->withRfc($this->rfc()->withQuery($raw === '' ? null : $raw));
            }

            $encoded = self::buildQuery($query);

            return $this->withRfc($this->rfc()->withQuery($encoded === '' ? null : $encoded));
        }
        catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'query',
                    'expected' => 'valid query map',
                    'received' => $query,
                ],
                previous: $exception,
            );
        }
    }

    /**
     * Set a single query parameter (overwrites when the key already exists).
     *
     * @param string  $key
     * @param mixed   $value
     *
     * @return static
     */
    public function withQueryParam(
        string $key,
        mixed  $value,
    ): static {
        $params       = $this->queryParams();
        $params[$key] = $value;

        return $this->withQuery($params);
    }

    /**
     * Remove a query parameter when present.
     *
     * @param string  $key
     *
     * @return static
     */
    public function withoutQueryParam(
        string $key,
    ): static {
        $params = $this->queryParams();
        unset($params[$key]);

        return $this->withQuery($params === [] ? null : $params);
    }

    /**
     * Merge query parameters into the current query (later keys override).
     *
     * @param array<string, mixed> $query
     *
     * @return static
     *
     * @throws InvalidArgumentException
     */
    public function mergeQuery(
        array $query,
    ): static {
        try {
            return $this->withQuery([...$this->queryParams(), ...$query]);
        }
        catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'query',
                    'expected' => 'valid query map',
                    'received' => $query,
                ],
                previous: $exception,
            );
        }
    }

    /**
     * Replace or clear the fragment.
     *
     * Pass null or an empty string to remove the fragment.
     *
     * @param null|string  $fragment
     *
     * @return static
     */
    public function withFragment(
        null|string $fragment,
    ): static {
        try {
            $fragment = $fragment === '' ? null : $fragment;
            return $this->withRfc($this->rfc()->withFragment($fragment));
        }
        catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'fragment',
                    'expected' => 'valid fragment',
                    'received' => $fragment,
                ],
                previous: $exception,
            );
        }
    }

    /**
     * Resolve `$reference` against this URI as base (RFC 3986 combine + remove_dot_segments).
     *
     * @param non-empty-string|\Stringable $reference Absolute URI or URI-reference
     *
     * @return static
     */
    public function resolve(
        string|\Stringable $reference,
    ): static {
        $string = \string($reference);

        try {
            return $this->withRfc($this->rfc()->resolve($string));
        }
        catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'reference',
                    'expected' => 'valid URI or URI-reference',
                    'received' => $reference,
                ],
                previous: $exception,
            );
        }
    }

    /**
     * Map a `file:` URI to a local {@see Path}.
     *
     * @throws RuntimeException When the scheme is not `file`
     */
    public function toPath(): Path
    {
        if (! $this->isFile()) {
            throw new RuntimeException(
                message: 'Only file: URIs can be converted to a Path.',
                context: [
                    'uri'    => $this->value,
                    'scheme' => $this->scheme(),
                ],
            );
        }

        $path = \rawurldecode($this->rfc()->getPath());

        if ($path === '') {
            throw new RuntimeException(
                message: 'file: URI has an empty path component.',
                context: ['uri' => $this->value],
            );
        }

        return new Path($path);
    }

    /**
     * Promote to an http(s) {@see Url} when the scheme allows transport.
     *
     * @throws RuntimeException When the URI is not an http(s) URL
     */
    public function asUrl(
        null|CurlInterface $http = null,
    ): Url {
        if (! $this->isHttp()) {
            throw new RuntimeException(
                message: 'Only http or https URIs can be promoted to a Url.',
                context: [
                    'uri'    => $this->value,
                    'scheme' => $this->scheme(),
                ],
            );
        }

        return new Url(
            uri : $this->value,
            http: $http,
        );
    }

    /**
     * Promote to an HTML-safe {@see Href}.
     */
    public function asHref(): Href
    {
        return new Href($this->value);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @throws InvalidArgumentException
     */
    private static function buildQuery(
        array $query,
    ): string {
        $pairs = [];

        foreach ($query as $key => $value) {
            if (! \is_string($key)) {
                throw new InvalidArgumentException(
                    context: [
                        'name'     => 'query',
                        'expected' => 'string keys',
                        'received' => $key,
                    ],
                );
            }

            if (\is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = \rawurlencode($key) . '=' . \rawurlencode(self::queryScalar($item));
                }

                continue;
            }

            $pairs[] = \rawurlencode($key) . '=' . \rawurlencode(self::queryScalar($value));
        }

        return \implode('&', $pairs);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function queryScalar(
        mixed $value,
    ): string {
        if ($value === null) {
            return '';
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (\is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException(
            context: [
                'name'     => 'query',
                'expected' => 'null|scalar|Stringable',
                'received' => $value,
            ],
        );
    }
}

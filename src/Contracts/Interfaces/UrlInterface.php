<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Stringable;

/**
 * Mutable public URL value object.
 *
 * Methods are expected to delegate to a {@see CurlInterface} collaborator.
 *
 * {@see UrlInterface::download()} produces a local {@see PathInterface}.
 *
 * Mutating methods update the instance in place and return {@see static} for chaining.
 */
interface UrlInterface extends Stringable
{
    /**
     * Normalized URL string.
     *
     * @var non-empty-string
     */
    public string $value { get; }

    /**
     * Appends a path segment, or merges a query string when `$string` starts with `?`.
     *
     * Query merges use later keys to override existing ones.
     */
    public function append(
        string|Stringable $string,
    ): static;

    /**
     * URL scheme (e.g. `https`), or an empty string for relative URLs.
     */
    public function scheme(): string;

    /**
     * Host component, or null when absent (typical for relative URLs).
     */
    public function host(): null|string;

    /**
     * Port component, or null when absent / default.
     */
    public function port(): null|int;

    /**
     * Path component, or an empty string when absent.
     */
    public function path(): string;

    /**
     * Parsed query parameters.
     *
     * @return array<array-key, mixed>
     */
    public function query(): array;

    /**
     * Fragment component without the leading `#`, or null when absent.
     */
    public function fragment(): null|string;

    /**
     * Whether this URL includes a scheme.
     */
    public function isAbsolute(): bool;

    /**
     * Whether this URL has no scheme (root- or path-relative).
     */
    public function isRelative(): bool;

    /**
     * Whether the scheme is `https`.
     */
    public function isSecure(): bool;

    /**
     * Whether {@see $value} has a valid URL shape.
     *
     * Structural only — does not perform an HTTP request.
     */
    public function isValid(): bool;

    /**
     * Sets the scheme (e.g. `http`, `https`).
     */
    public function withScheme(
        string $scheme,
    ): static;

    /**
     * Sets or clears the host.
     *
     * Pass null to remove the host component.
     */
    public function withHost(
        null|string $host,
    ): static;

    /**
     * Replaces the path component.
     */
    public function withPath(
        string $path,
    ): static;

    /**
     * Sets or clears the fragment.
     *
     * Pass null or an empty string to remove the fragment.
     */
    public function withFragment(
        null|string $fragment,
    ): static;

    /**
     * Replaces the entire query string.
     *
     * @param array<string, mixed>|string $query Parsed map or raw query string (without `?`).
     */
    public function withQuery(
        array|string $query,
    ): static;

    /**
     * Sets a single query parameter (overwrites when the key already exists).
     */
    public function withQueryParam(
        string $key,
        mixed $value,
    ): static;

    /**
     * Removes a query parameter when present.
     */
    public function withoutQueryParam(
        string $key,
    ): static;

    /**
     * Merges query parameters into the current query (later keys override).
     *
     * @param array<string, mixed> $query
     */
    public function mergeQuery(
        array $query,
    ): static;

    /**
     * Whether the endpoint responds with an HTTP 2xx or 3xx status.
     *
     * Thin convenience over {@see probe()}.
     *
     * @throws \Northrook\Contracts\Exceptions\CurlException When `$throw` is true and the request fails.
     */
    public function exists(
        bool $throw = false,
    ): bool;

    /**
     * Probes the endpoint (typically HEAD or a lightweight GET).
     *
     * @param array<string, mixed> $options Transport options forwarded to {@see CurlInterface}.
     *
     * @throws \Northrook\Contracts\Exceptions\CurlException When `$throw` is true and the request fails.
     */
    public function probe(
        bool $throw = false,
        array $options = [],
    ): bool;

    /**
     * Fetches the response body for this URL.
     *
     * @param array<string, mixed> $options Transport options forwarded to {@see CurlInterface}.
     *
     * @throws \Northrook\Contracts\Exceptions\CurlException When the request fails.
     */
    public function fetch(
        array $options = [],
    ): string;

    /**
     * Downloads the resource to disk and returns a local {@see PathInterface}.
     *
     * When `$destination` is null, a temporary file path is used.
     *
     * @throws \Northrook\Contracts\Exceptions\CurlException When the download fails.
     */
    public function download(
        null|string|Stringable $destination = null,
    ): PathInterface;
}

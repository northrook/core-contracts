<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP facade over Symfony {@see HttpClientInterface} (typically {@see \Symfony\Component\HttpClient\CurlHttpClient}).
 *
 * Caching:
 * - Symfony does not dedupe separate method calls; each verb/inspect/probe may hit the network.
 * - Prefer {@see inspect()} when several checks need the same metadata, then derive status/headers locally.
 * - Soft (process) memoization of successful probe/inspect metadata is a handler concern — opt-in via
 *   `$cached` where present. Not a substitute for {@see \Symfony\Component\HttpClient\CachingHttpClient}.
 * - Local download I/O (temp file, resume, atomic promote) belongs on the handler via
 *   {@see \Northrook\Contracts\FilesystemInterface}.
 */
interface CurlInterface
{
    /**
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS} overrides
     */
    public function client(
        array $options = [],
    ): HttpClientInterface;

    /**
     * Generic request escape hatch.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function request(
        string $method,
        string $url,
        array  $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed> $query   Query parameters merged into the URL
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function get(
        string $url,
        array  $query = [],
        array  $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function post(
        string $url,
        mixed  $body = '',
        array  $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function head(
        string $url,
        array  $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function put(
        string $url,
        mixed  $body = '',
        array  $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function patch(
        string $url,
        mixed  $body = '',
        array  $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function delete(
        string $url,
        array  $options = [],
    ): ResponseInterface;

    /**
     * Fetch the response body as a string.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     */
    public function fetch(
        string $url,
        array  $options = [],
    ): string;

    /**
     * Send a request with an optional JSON body and decode the response as JSON.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException On transport failure
     * @throws \JsonException When the response body is not valid JSON
     */
    public function json(
        string $method,
        string $url,
        mixed  $data = null,
        array  $options = [],
    ): mixed;

    /**
     * Download `$url` to a file path or stream callback.
     *
     * String `$location`: stream to a hashed temp file, resuming an interrupted download
     * via `Range` when the server honors it, then promote to the destination only once the
     * payload is complete and validated. Returns the final resolved path, or `false` on
     * failure (a resumable temp file may be kept for the next call).
     *
     * Callable `$location`: deliver a readable stream handle; always a full GET, no resume.
     *
     * @return string|bool Final resolved path for string `$location`; `bool` for callable
     */
    public function download(
        string          $url,
        string|callable $location,
    ): string|bool;

    /**
     * Lightweight metadata request (typically HEAD): status, headers, effective URL.
     *
     * Prefer this when several checks would otherwise each trigger their own request.
     * When `$cached` is true, handlers may soft-memoize successful results for the process lifetime.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @return array{
     *     status: int,
     *     headers: array<string, list<string>>,
     *     url: string
     * }
     *
     * @throws CurlException On transport failure
     */
    public function inspect(
        string $url,
        bool   $cached = true,
        array  $options = [],
    ): array;

    /**
     * Response headers only (typically via {@see inspect()} / HEAD).
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @return array<string, list<string>> Header names keyed lowercase, Symfony-style
     *
     * @throws CurlException On transport failure
     */
    public function headers(
        string $url,
        bool   $cached = true,
        array  $options = [],
    ): array;

    /**
     * Whether `$url` responds with an HTTP 2xx or 3xx status.
     *
     * Default options: `timeout` 5, `max_redirects` 20.
     * When `$cached` is true, handlers may soft-memoize successful results for the process lifetime.
     *
     * @param array<string, mixed> $options Symfony {@see HttpClientInterface::OPTIONS_DEFAULTS}
     *
     * @throws CurlException When `$throwOnError` is true and the request fails
     */
    public function probeUrl(
        string $url,
        bool   $throwOnError = false,
        bool   $cached = true,
        array  $options = [],
    ): bool;
}

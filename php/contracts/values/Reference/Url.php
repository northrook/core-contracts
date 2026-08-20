<?php

declare(strict_types=1);

namespace Northrook\Reference;

use Northrook\Context;
use Northrook\CurlException;
use Northrook\CurlInterface;
use Northrook\DependencyException;
use Northrook\Filesystem\File;
use Northrook\Filesystem\Path;
use Northrook\InvalidArgumentException;
use Uri\WhatWg\InvalidUrlException;

use function Northrook\get_temp_path;

/**
 * Absolute http(s) network URL {@see Reference}.
 *
 * Subclass of {@see Uri} for `http://` / `https://` locators. Parsed with
 * {@see \Uri\WhatWg\Url} so IDN hosts and Unicode paths are accepted and stored
 * in ASCII-canonical form.
 *
 * Rejects URI-references, opaque URIs (`mailto:`, `urn:`), non-http(s) schemes,
 * empty hosts, and single-character schemes (drive-letter footgun — use
 * {@see Path} / {@see File} for filesystem locations).
 *
 * Transport helpers ({@see probe()}, {@see fetch()}, {@see download()}) use
 * the injected {@see CurlInterface}, then the registered {@see Context} client.
 */
final class Url extends Uri
{
    /**
     * Builds from `$uri`, optionally resolving against `$base`.
     *
     * @param string|\Stringable $uri  Absolute http(s) URL
     * @param null|Uri           $base Base for relative resolution; ignored when `$uri` is absolute
     * @param null|CurlInterface $http HTTP collaborator for transport helpers
     *
     * @throws InvalidArgumentException When `$uri` is not an http(s) URL for this type
     */
    public function __construct(
        string|\Stringable                  $uri,
        null|Uri                            $base = null,
        private readonly null|CurlInterface $http = null,
    ) {
        parent::__construct($uri, $base);
    }

    /**
     * ASCII-canonical http(s) URL (`http(s)://` + non-empty host).
     *
     * {@inheritDoc}
     *
     * @return non-empty-string
     */
    public static function normalize(
        string|\Stringable $value,
    ): string {
        $string = (string) $value;

        if ($string === '') {
            throw new InvalidArgumentException(
                message: 'URL cannot be empty.',
                context: [
                    'name'     => 'url',
                    'expected' => 'non-empty URL',
                    'received' => $value,
                ],
            );
        }

        try {
            $parsed = new \Uri\WhatWg\Url($string);
        } catch (InvalidUrlException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'url',
                    'expected' => 'valid http or https URL',
                    'received' => $value,
                ],
                previous: $exception,
            );
        }

        $scheme = $parsed->getScheme();

        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new InvalidArgumentException(
                message: "Scheme '{$scheme}' is not supported; only http and https URLs are accepted.",
                context: [
                    'name'     => 'url',
                    'expected' => 'http or https scheme',
                    'received' => $value,
                ],
            );
        }

        $host = $parsed->getAsciiHost();

        if ($host === null || $host === '') {
            throw new InvalidArgumentException(
                message: 'URL must include a non-empty host (scheme://…).',
                context: [
                    'name'     => 'url',
                    'expected' => 'http(s) URL with non-empty host',
                    'received' => $value,
                ],
            );
        }

        $normalized = $parsed->toAsciiString();

        if ($normalized === '') {
            throw new InvalidArgumentException(
                message: 'URL normalized to an empty string.',
                context: [
                    'name'     => 'url',
                    'expected' => 'non-empty URL',
                    'received' => $value,
                ],
            );
        }

        return $normalized;
    }

    /**
     * Promote to an HTML-safe {@see Href}.
     */
    public function asHref(): Href
    {
        return new Href($this->value);
    }

    /**
     * @param \Uri\Rfc3986\Uri  $uri
     *
     * @return static
     */
    protected function withRfc(
        \Uri\Rfc3986\Uri $uri,
    ): static {
        return new static(
            uri : $uri->toString(),
            http: $this->http,
        );
    }

    /**
     * @throws DependencyException When no {@see CurlInterface} was provided
     */
    private function httpClient(
        string $method,
    ): CurlInterface {
        $http = $this->http ?? Context::tryGet()?->curlClient;

        if ($http !== null) {
            return $http;
        }

        throw new DependencyException(
            message: "`{$method}` requires an injected or globally registered CurlInterface instance.",
            context: [
                'method' => $method,
                'url'    => $this->value,
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Transport
    // -------------------------------------------------------------------------

    /**
     * Whether the endpoint responds with an HTTP 2xx or 3xx status.
     *
     * Thin convenience over {@see probe()}. Not a structural validity check.
     *
     * @throws DependencyException When no {@see CurlInterface} was provided
     */
    public function exists(
        bool $throw = false,
    ): bool {
        return $this->probe($throw);
    }

    /**
     * Lightweight reachability probe (typically HEAD or a minimal GET).
     *
     * @param array<string, mixed> $options Transport options forwarded to the HTTP client
     *
     * @throws DependencyException When no {@see CurlInterface} was provided
     */
    public function probe(
        bool  $throw = false,
        array $options = [],
    ): bool {
        return $this->httpClient(__METHOD__)->probeUrl(
            url         : $this->value,
            throwOnError: $throw,
            options     : $options,
        );
    }

    /**
     * Fetch the response body for this URL.
     *
     * @param array<string, mixed> $options Transport options forwarded to the HTTP client
     *
     * @throws CurlException         When the request fails
     * @throws DependencyException When no {@see CurlInterface} was provided
     */
    public function fetch(
        array $options = [],
    ): string {
        $http = $this->httpClient(__METHOD__);

        try {
            return $http->get($this->value, options: $options)->getContent();
        } catch (CurlException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new CurlException(
                url     : $this->value,
                message : $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    /**
     * Download the resource to disk and return a local {@see File}.
     *
     * When `$destination` is null, a temporary file path is used.
     *
     * @throws CurlException         When the download fails
     * @throws DependencyException When no {@see CurlInterface} was provided
     */
    public function download(
        null|string|\Stringable|Path|File $destination = null,
    ): File {
        $location = $destination === null
            ? get_temp_path('download')
            : (string) $destination;

        $path = $this->httpClient(__METHOD__)->download($this->value, $location);

        if (! \is_string($path)) {
            throw new CurlException(
                url    : $this->value,
                message: "Download of '{$this->value}' to '{$location}' failed.",
                context: ['destination' => $location],
            );
        }

        return new File($path, assert: true);
    }
}

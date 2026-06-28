<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Northrook\Contracts\Exceptions\CurlException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

interface CurlInterface
{
    /**
     * @param array<string, mixed>  $options
     */
    public function client(
        array $options = [],
    ): HttpClientInterface;

    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $options
     */
    public function get(
        string $url,
        array $query = [],
        array $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed>  $options
     */
    public function post(
        string $url,
        mixed $body = '',
        array $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed>  $options
     */
    public function head(
        string $url,
        array $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed>  $options
     */
    public function put(
        string $url,
        mixed $body = '',
        array $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed>  $options
     */
    public function patch(
        string $url,
        mixed $body = '',
        array $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed>  $options
     */
    public function delete(
        string $url,
        array $options = [],
    ): ResponseInterface;

    /**
     * @param array<string, mixed>  $options
     *
     * @throws \JsonException
     */
    public function json(
        string $method,
        string $url,
        mixed $data = null,
        array $options = [],
    ): mixed;

    public function download(
        string $url,
        string|callable $location,
    ): bool;

    /**
     * Check if a given `$url` returns an HTTP 2xx or 3xx status.
     *
     * Default options: `timeout` 5, `max_redirects` 20.
     *
     * @param array<string, mixed>  $options
     *
     * @throws CurlException When `$throwOnError` is true and the request fails
     */
    public function probeUrl(
        string $url,
        bool $throwOnError = false,
        bool $cached = true,
        array $options = [],
    ): bool;
}

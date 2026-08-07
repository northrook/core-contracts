<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP facade over Symfony {@see HttpClientInterface} (typically {@see CurlHttpClient}).
 *
 * Soft-memoizes successful {@see inspect()} metadata per instance when `$cached`
 * is true (keyed by URL + options fingerprint). Download I/O (temp file, resume,
 * promote) prefers an injected {@see FilesystemInterface} when present; otherwise
 * uses simple native PHP.
 */
final class CURL implements CurlInterface
{
    use FilesystemTrait;

    /**
     * @var array<string, array{
     *     status: int,
     *     headers: array<string, list<string>>,
     *     url: string
     * }>
     */
    private array $inspectCache = [];

    /** @var array<string, mixed> */
    private readonly array $defaultOptions;

    private readonly int $maxHostConnections;

    private readonly int $maxPendingPushes;

    private readonly LoggerInterface $logger;

    private readonly string $cacheDirectory;

    private readonly null|HttpClientInterface $httpClient;

    /**
     * @param array<string, mixed>     $defaultOptions     Applied as Symfony client defaults (nested keys like `headers` merge via {@see HttpClientInterface::withOptions()})
     * @param int                      $maxHostConnections Passed to {@see CurlHttpClient} / {@see HttpClient::create()}
     * @param int                      $maxPendingPushes   Passed to {@see CurlHttpClient} / {@see HttpClient::create()}
     * @param null|HttpClientInterface $httpClient         When set, used as the base client (defaults baked via {@see HttpClientInterface::withOptions()})
     */
    public function __construct(
        array                    $defaultOptions = [],
        int                      $maxHostConnections = 6,
        int                      $maxPendingPushes = 0,
        null|LoggerInterface     $logger = null,
        null|FilesystemInterface $filesystem = null,
        null|string              $cacheDirectory = null,
        null|HttpClientInterface $httpClient = null,
    ) {
        $this->defaultOptions     = $defaultOptions;
        $this->maxHostConnections = $maxHostConnections;
        $this->maxPendingPushes   = $maxPendingPushes;
        $this->logger             = $logger ?? new NullLogger;
        $this->filesystem         = $filesystem;
        $this->cacheDirectory     = $cacheDirectory ?? Normalize::path([
                \sys_get_temp_dir(),
                'northrook-curl',
            ]);
        $this->httpClient =
            $httpClient === null || $defaultOptions === []
                ? $httpClient
                : $httpClient->withOptions($defaultOptions);
    }

    /**
     * {@inheritDoc}
     *
     * When no `$httpClient` was injected, creates a Symfony client per call
     * ({@see HttpClient::create()} or {@see CurlHttpClient}) with `$defaultOptions`,
     * then applies per-call `$options` via {@see HttpClientInterface::withOptions()}
     * so nested maps (`headers`, `extra`, …) merge correctly. Requires
     * `symfony/http-client` unless a client was injected.
     */
    public function client(
        array $options = [],
    ): HttpClientInterface {
        if ($this->httpClient !== null) {
            return $options === []
                ? $this->httpClient
                : $this->httpClient->withOptions($options);
        }

        if (\class_exists(HttpClient::class)) {
            $client = HttpClient::create(
                $this->defaultOptions,
                $this->maxHostConnections,
                $this->maxPendingPushes,
            );
        } elseif (\class_exists(CurlHttpClient::class)) {
            $client = new CurlHttpClient(
                $this->defaultOptions,
                $this->maxHostConnections,
                $this->maxPendingPushes,
            );
        } else {
            throw new DependencyException(
                message: 'CURL::client() requires symfony/http-client, or an injected HttpClientInterface.',
                context: [
                    'method' => __METHOD__,
                    'class'  => self::class,
                ],
            );
        }

        if (\method_exists($client, 'setLogger')) {
            $client->setLogger($this->logger);
        }

        return $options === []
            ? $client
            : $client->withOptions($options);
    }

    public function request(
        string $method,
        string $url,
        array  $options = [],
    ): ResponseInterface {
        try {
            return $this->client($options)->request($method, $url);
        } catch (\Throwable $exception) {
            throw new CurlException($url, previous: $exception);
        }
    }

    public function get(
        string $url,
        array  $query = [],
        array  $options = [],
    ): ResponseInterface {
        if ($query !== []) {
            $existingQuery    = $options['query'] ?? [];
            $options['query'] = \array_replace(
                \is_array($existingQuery) ? $existingQuery : [],
                $query,
            );
        }

        return $this->request('GET', $url, $options);
    }

    public function post(
        string $url,
        mixed  $body = '',
        array  $options = [],
    ): ResponseInterface {
        return $this->requestWithBody('POST', $url, $body, $options);
    }

    public function head(
        string $url,
        array  $options = [],
    ): ResponseInterface {
        return $this->request('HEAD', $url, $options);
    }

    public function put(
        string $url,
        mixed  $body = '',
        array  $options = [],
    ): ResponseInterface {
        return $this->requestWithBody('PUT', $url, $body, $options);
    }

    public function patch(
        string $url,
        mixed  $body = '',
        array  $options = [],
    ): ResponseInterface {
        return $this->requestWithBody('PATCH', $url, $body, $options);
    }

    public function delete(
        string $url,
        array  $options = [],
    ): ResponseInterface {
        return $this->request('DELETE', $url, $options);
    }

    public function fetch(
        string $url,
        array  $options = [],
    ): string {
        try {
            return $this->get($url, options: $options)->getContent();
        } catch (CurlException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new CurlException($url, previous: $exception);
        }
    }

    public function json(
        string $method,
        string $url,
        mixed  $data = null,
        array  $options = [],
    ): mixed {
        if ($data !== null) {
            $options['json'] = $data;
        }

        try {
            $content = $this->request($method, $url, $options)->getContent();
        } catch (CurlException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new CurlException($url, previous: $exception);
        }

        return \json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    public function download(
        string          $url,
        string|callable $location,
    ): string|bool {
        return \is_callable($location)
            ? $this->downloadToCallable($url, $location)
            : $this->downloadToFile($url, $location);
    }

    public function inspect(
        string $url,
        bool   $cached = true,
        array  $options = [],
    ): array {
        $options = \array_replace([
            'timeout'       => 5,
            'max_redirects' => 20,
        ], $options);

        $cacheKey = $url . "\0" . \hash('xxh3', \serialize($options));

        if ($cached && isset($this->inspectCache[$cacheKey])) {
            return $this->inspectCache[$cacheKey];
        }

        try {
            $response     = $this->head($url, $options);
            $effectiveUrl = $response->getInfo('url');
            $meta         = [
                'status'  => $response->getStatusCode(),
                'headers' => $response->getHeaders(false),
                'url'     => \is_string($effectiveUrl) && $effectiveUrl !== ''
                    ? $effectiveUrl
                    : $url,
            ];
        } catch (CurlException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new CurlException($url, previous: $exception);
        }

        if ($cached && self::isSuccessStatus($meta['status'])) {
            $this->inspectCache[$cacheKey] = $meta;
        }

        return $meta;
    }

    public function headers(
        string $url,
        bool   $cached = true,
        array  $options = [],
    ): array {
        return $this->inspect($url, $cached, $options)['headers'];
    }

    /**
     * @throws \Throwable
     */
    public function probeUrl(
        string $url,
        bool   $throwOnError = false,
        bool   $cached = true,
        array  $options = [],
    ): bool {
        try {
            $status = $this->inspect($url, $cached, $options)['status'];
        } catch (\Throwable $exception) {
            if ($throwOnError) {
                if ($exception instanceof CurlException) {
                    throw $exception;
                }

                throw new CurlException($url, previous: $exception);
            }

            return false;
        }

        $success = self::isSuccessStatus($status);

        if (! $success && $throwOnError) {
            throw new CurlException($url);
        }

        return $success;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function requestWithBody(
        string $method,
        string $url,
        mixed  $body,
        array  $options,
    ): ResponseInterface {
        if ($body !== '' && ! \array_key_exists('body', $options) && ! \array_key_exists('json', $options)) {
            $options['body'] = $body;
        }

        return $this->request($method, $url, $options);
    }

    /**
     * Stream a download into a temporary file and deliver the handle to `$callback`.
     */
    private function downloadToCallable(
        string   $url,
        callable $callback,
    ): bool {
        $handle = \tmpfile();

        if ($handle === false) {
            $this->logger->error('Failed to open temporary file for download.', ['url' => $url]);

            return false;
        }

        $status = $this->streamToHandle($url, $handle, []);

        if ($status !== 200) {
            $this->logger->error('Download failed with unexpected status.', [
                'url'    => $url,
                'status' => $status,
            ]);
            \fclose($handle);

            return false;
        }

        \rewind($handle);
        $callback($handle);
        \fclose($handle);

        return true;
    }

    /**
     * Download to `$location` through a hashed temp file, resuming an interrupted
     * download when the server honors `Range`.
     *
     * The temp file only ever holds a validated prefix of the payload: response
     * status and headers are inspected before any body byte is written, and a
     * failed append is rolled back to the pre-attempt size. The destination is
     * written only once the payload is provably complete.
     *
     * @return string|false Final resolved destination path, `false` on failure
     */
    private function downloadToFile(
        string $url,
        string $location,
    ): string|false {
        $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs) => $fs->createDirectory($this->cacheDirectory),
            fallback: function(): void {
                if (! \is_dir($this->cacheDirectory)) {
                    @\mkdir($this->cacheDirectory, 0777, true);
                }
            },
        );

        $destination = $this->resolveDownloadPath($url, $location);
        $tempFile    = $this->tempFilePath($destination);

        return $this->downloadToTemp($url, $destination, $tempFile, allowResume: true);
    }

    /**
     * GET `$url` into `$tempFile`, promoting to `$destination` once complete.
     *
     * When `$allowResume` is false, no `Range` header is sent — guards against
     * restart loops after an abandoned resume attempt.
     */
    private function downloadToTemp(
        string $url,
        string $destination,
        string $tempFile,
        bool   $allowResume,
    ): string|false {
        $resumeSize = $allowResume ? $this->tempFileSize($tempFile) : 0;

        $options = [];

        if ($resumeSize > 0) {
            $options['headers'] = ['Range' => 'bytes=' . $resumeSize . '-'];
        }

        $client = $this->client($options);

        try {
            $response = $client->request('GET', $url);
            // Inspected before any body byte is consumed or written.
            $status  = $response->getStatusCode();
            $headers = $response->getHeaders(false);
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'url'       => $url,
                'exception' => $exception,
            ]);

            return false;
        }

        if ($resumeSize > 0) {
            return $this->resolveResumeAttempt(
                $client,
                $response,
                $status,
                $headers,
                $url,
                $destination,
                $tempFile,
                $resumeSize,
            );
        }

        if ($status !== 200) {
            $this->logger->error('Download failed with unexpected status.', [
                'url'    => $url,
                'status' => $status,
            ]);
            $this->removeTempFile($tempFile);

            return false;
        }

        return $this->writeFreshBody($client, $response, $headers, $url, $destination, $tempFile);
    }

    /**
     * Decide how to handle the response to a `Range` resume attempt.
     *
     * @param array<string, list<string>> $headers
     * @param int<1, max>                 $resumeSize
     */
    private function resolveResumeAttempt(
        HttpClientInterface $client,
        ResponseInterface   $response,
        int                 $status,
        array               $headers,
        string              $url,
        string              $destination,
        string              $tempFile,
        int                 $resumeSize,
    ): string|false {
        if ($status === 206) {
            return $this->appendResumeBody(
                $client,
                $response,
                $headers,
                $url,
                $destination,
                $tempFile,
                $resumeSize,
            );
        }

        if ($status === 416) {
            $total = $this->resolveRangeTotal($headers, $url);

            if ($total !== null && $total === $resumeSize) {
                return $this->promoteTemp($url, $tempFile, $destination);
            }

            $this->logger->warning('Resume rejected (416) but temp is incomplete; restarting download.', [
                'url'      => $url,
                'tempSize' => $resumeSize,
                'total'    => $total,
            ]);
            $this->removeTempFile($tempFile);

            return $this->downloadToTemp($url, $destination, $tempFile, allowResume: false);
        }

        if ($status === 200) {
            // Range ignored: the still-unconsumed full body is written from scratch.
            $this->logger->info('Server ignored the Range request; restarting download from scratch.', [
                'url' => $url,
            ]);

            return $this->writeFreshBody($client, $response, $headers, $url, $destination, $tempFile);
        }

        $this->logger->error('Resume attempt failed with unexpected status.', [
            'url'      => $url,
            'status'   => $status,
            'tempSize' => $resumeSize,
        ]);

        return false;
    }

    /**
     * Validate a 206 response against the current temp size, then append its body.
     *
     * @param array<string, list<string>> $headers
     * @param int<1, max>                 $resumeSize
     */
    private function appendResumeBody(
        HttpClientInterface $client,
        ResponseInterface   $response,
        array               $headers,
        string              $url,
        string              $destination,
        string              $tempFile,
        int                 $resumeSize,
    ): string|false {
        $contentRange = self::firstHeader($headers, 'content-range');
        $encoding     = \strtolower(self::firstHeader($headers, 'content-encoding') ?? '');
        $range        = self::parseContentRange($contentRange ?? '');

        $valid =
            $range !== null
            && $range['start'] === $resumeSize
            && ( $range['total'] === null || ( $range['end'] + 1 ) <= $range['total'] )
            && ! \in_array($encoding, ['gzip', 'br', 'deflate', 'compress', 'zstd'], true);

        if (! $valid) {
            $this->logger->warning('Resume response failed validation; restarting download.', [
                'url'              => $url,
                'tempSize'         => $resumeSize,
                'content-range'    => $contentRange,
                'content-encoding' => $encoding === '' ? null : $encoding,
            ]);
            $this->removeTempFile($tempFile);

            return $this->downloadToTemp($url, $destination, $tempFile, allowResume: false);
        }

        $handle = \fopen($tempFile, 'a+b');

        if ($handle === false) {
            $this->logger->error('Failed to open temporary download file.', [
                'url'  => $url,
                'path' => $tempFile,
            ]);

            return false;
        }

        try {
            $written = $this->streamBodyToHandle($client, $response, $handle);

            // Transport failure mid-append: the appended bytes are the exact
            // continuation of the prefix, so the temp stays resumable.
            if ($written === false) {
                return false;
            }

            $expected = $range['end'] - $range['start'] + 1;

            if ($written !== $expected) {
                // The body did not match its Content-Range promise; roll back.
                \ftruncate($handle, $resumeSize);
                \fflush($handle);
                $this->logger->warning('Resumed body size mismatch; rolled back appended bytes.', [
                    'url'      => $url,
                    'expected' => $expected,
                    'written'  => $written,
                ]);

                return false;
            }
        } finally {
            \fclose($handle);
        }

        if ($range['total'] !== null && ( $range['end'] + 1 ) < $range['total']) {
            // Valid prefix with more ranges remaining; the next call resumes.
            $this->logger->info('Resume incomplete; temp kept for the next attempt.', [
                'url'   => $url,
                'size'  => $range['end'] + 1,
                'total' => $range['total'],
            ]);

            return false;
        }

        // `total` unknown (`*`): weaker guarantee — finished stream + validated start.
        return $this->promoteTemp($url, $tempFile, $destination);
    }

    /**
     * Write a full (non-resume) response body to `$tempFile`, truncating any prefix.
     *
     * @param array<string, list<string>> $headers
     */
    private function writeFreshBody(
        HttpClientInterface $client,
        ResponseInterface   $response,
        array               $headers,
        string              $url,
        string              $destination,
        string              $tempFile,
    ): string|false {
        $handle = \fopen($tempFile, 'w+b');

        if ($handle === false) {
            $this->logger->error('Failed to open temporary download file.', [
                'url'  => $url,
                'path' => $tempFile,
            ]);

            return false;
        }

        try {
            $written = $this->streamBodyToHandle($client, $response, $handle);
        } finally {
            \fclose($handle);
        }

        // Partial body: the temp holds a valid prefix; keep it so the next call resumes.
        if ($written === false) {
            return false;
        }

        $contentLength = self::firstHeader($headers, 'content-length');

        if ($contentLength !== null && \ctype_digit(\trim($contentLength)) && $written !== (int) $contentLength) {
            $this->logger->info('Download truncated; temp kept for resume.', [
                'url'      => $url,
                'expected' => (int) $contentLength,
                'written'  => $written,
            ]);

            return false;
        }

        return $this->promoteTemp($url, $tempFile, $destination);
    }

    /**
     * Total payload size for a 416 response: from `Content-Range: bytes *​/<total>`,
     * falling back to a HEAD request.
     *
     * @param array<string, list<string>> $headers
     */
    private function resolveRangeTotal(
        array  $headers,
        string $url,
    ): null|int {
        $contentRange = self::firstHeader($headers, 'content-range');

        if ($contentRange !== null && \preg_match('~^bytes\s+\*/(\d+)$~i', \trim($contentRange), $matches)) {
            return (int) $matches[1];
        }

        try {
            $contentLength = self::firstHeader(
                $this->inspect($url, cached: false)['headers'],
                'content-length',
            );
        } catch (\Throwable) {
            return null;
        }

        return $contentLength !== null && \ctype_digit(\trim($contentLength))
            ? (int) $contentLength
            : null;
    }

    /**
     * Copy a completed temp file to its destination and remove the temp.
     *
     * On copy failure the temp is retained so a later download can re-promote
     * without re-fetching (e.g. via a 416 when the temp already matches the total).
     *
     * @return string|false The destination path on success
     */
    private function promoteTemp(
        string $url,
        string $tempFile,
        string $destination,
    ): string|false {
        try {
            $this->filesystem(
                __METHOD__,
                filesystem: fn(FilesystemInterface $fs) => $fs->copyFile(
                    $tempFile,
                    $destination,
                    alwaysOverwrite: true,
                ),
                fallback: function() use ($tempFile, $destination): void {
                    $parent = \dirname($destination);

                    if ($parent !== '' && $parent !== '.' && ! \is_dir($parent)) {
                        @\mkdir($parent, 0777, true);
                    }

                    if (! \copy($tempFile, $destination)) {
                        throw new FilesystemException(
                            message: "Unable to copy '{$tempFile}' to '{$destination}'.",
                            path   : $destination,
                        );
                    }
                },
            );
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'url'         => $url,
                'destination' => $destination,
                'exception'   => $exception,
                'tempKept'    => true,
            ]);

            return false;
        }

        // Destination is already good — cleanup failure must not report download failure.
        try {
            $this->removeTempFile($tempFile);
        } catch (\Throwable $exception) {
            $this->logger->warning($exception->getMessage(), [
                'url'         => $url,
                'destination' => $destination,
                'tempFile'    => $tempFile,
                'exception'   => $exception,
            ]);
        }

        return $destination;
    }

    /**
     * Current size of a download temp file, `0` when absent or unreadable.
     *
     * @return int<0, max>
     */
    private function tempFileSize(
        string $tempFile,
    ): int {
        $readable = $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): bool => $fs->isReadable($tempFile),
            fallback: fn(): bool => \is_readable($tempFile),
        );

        if (! $readable) {
            return 0;
        }

        return \max(0, $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): int => $fs->fileSize($tempFile),
            fallback: fn(): int => (int) \filesize($tempFile),
        ));
    }

    /**
     * Stream a response body into `$handle` chunk by chunk, flushing each write.
     *
     * Must use the same client that issued the request. Bytes written before a
     * transport failure remain in `$handle` — always an exact prefix of the body.
     *
     * @param resource $handle
     *
     * @return int|false Bytes written, `false` on transport or write failure
     */
    private function streamBodyToHandle(
        HttpClientInterface $client,
        ResponseInterface   $response,
        mixed               $handle,
    ): int|false {
        $written = 0;

        try {
            foreach ($client->stream($response) as $chunk) {
                if ($chunk->isTimeout() || $chunk->isFirst()) {
                    continue;
                }

                $content = $chunk->getContent();

                if ($content !== '') {
                    $offset = 0;
                    $length = \strlen($content);

                    while ($offset < $length) {
                        $bytes = \fwrite($handle, \substr($content, $offset));

                        if ($bytes === false || $bytes === 0) {
                            $this->logger->error('Failed writing download body to file handle.', [
                                'url' => $response->getInfo('url'),
                            ]);

                            return false;
                        }

                        $offset += $bytes;
                    }

                    \fflush($handle);
                    $written += $length;
                }

                if ($chunk->isLast()) {
                    break;
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'url'       => $response->getInfo('url'),
                'exception' => $exception,
            ]);

            return false;
        }

        return $written;
    }

    /**
     * Parse a `Content-Range: bytes <start>-<end>/<total|*>` header.
     *
     * @return null|array{start: int, end: int, total: null|int}
     */
    private static function parseContentRange(
        string $header,
    ): null|array {
        if (! \preg_match('~^bytes\s+(\d+)-(\d+)/(\d+|\*)$~i', \trim($header), $matches)) {
            return null;
        }

        $start = (int) $matches[1];
        $end   = (int) $matches[2];

        if ($end < $start) {
            return null;
        }

        return [
            'start' => $start,
            'end'   => $end,
            'total' => $matches[3] === '*' ? null : (int) $matches[3],
        ];
    }

    /**
     * First value of a Symfony-style lowercase-keyed header list.
     *
     * @param array<string, list<string>> $headers
     */
    private static function firstHeader(
        array  $headers,
        string $name,
    ): null|string {
        return $headers[\strtolower($name)][0] ?? null;
    }

    /**
     * GET `$url` while writing the response body to `$handle` via Symfony's `buffer` option.
     *
     * @param resource             $handle
     * @param array<string, mixed> $options
     *
     * @return int HTTP status code, or `0` on transport failure
     */
    private function streamToHandle(
        string $url,
        mixed  $handle,
        array  $options,
    ): int {
        $options['buffer'] = $handle;

        try {
            $response = $this->client($options)->request('GET', $url);
            $status   = $response->getStatusCode();

            // Drain the response so buffered content is fully written.
            (void) $response->getContent(false);

            return $status;
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'url'       => $url,
                'exception' => $exception,
            ]);

            return 0;
        }
    }

    /**
     * Normalize `$location` and, when it has no extension, append the URL basename.
     *
     * Query and fragment are stripped from the URL path before taking the basename.
     */
    private function resolveDownloadPath(
        string $url,
        string $location,
    ): string {
        $location = Normalize::path($location);

        $urlPath     = \parse_url($url, \PHP_URL_PATH);
        $urlBasename = \is_string($urlPath) && $urlPath !== '' && $urlPath !== '/'
            ? \strrchr($urlPath, '/')
            : false;
        $pathBasename = \strrchr($location, '/');

        if (
            $urlBasename !== false
            && $pathBasename !== false
            && $urlBasename !== $pathBasename
            && ! \str_contains($pathBasename, '.')
        ) {
            $location .= '/' . \ltrim($urlBasename, '/');
        }

        return Normalize::path($location);
    }

    /** Path of the hashed temp file used while downloading to `$destination`. */
    private function tempFilePath(
        string $destination,
    ): string {
        return Normalize::path([
            $this->cacheDirectory,
            \hash('xxh32', $destination) . '.tmp',
        ]);
    }

    private function removeTempFile(
        string $path,
    ): void {
        $readable = $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs): bool => $fs->isReadable($path),
            fallback: fn(): bool => \is_readable($path),
        );

        if (! $readable) {
            return;
        }

        $this->filesystem(
            __METHOD__,
            filesystem: fn(FilesystemInterface $fs) => $fs->remove($path),
            fallback: static fn() => @\unlink($path),
        );
    }

    private static function isSuccessStatus(
        int $status,
    ): bool {
        return $status >= 200 && $status < 400;
    }
}

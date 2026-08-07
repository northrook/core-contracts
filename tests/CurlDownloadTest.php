<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\CURL;
use Northrook\Contracts\FilesystemException;
use Northrook\Contracts\Tests\Support\FilesystemStub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CurlDownloadTest extends TestCase
{
    private const string URL = 'https://example.com/file.bin';

    private const string PAYLOAD = 'full-payload-content-for-resume-testing';

    private string $workDir;

    private string $cacheDir;

    private string $destination;

    protected function setUp(): void
    {
        $this->workDir     = \sys_get_temp_dir() . '/curl-download-test-' . \bin2hex(\random_bytes(6));
        $this->cacheDir    = $this->workDir . '/cache';
        $this->destination = $this->workDir . '/target.bin';
        \mkdir($this->workDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDir);
    }

    public function testResumeCompletesValidPartialDownload(): void
    {
        $resumeSize = 10;
        $this->seedTempFile(\substr(self::PAYLOAD, 0, $resumeSize));

        $curl = $this->curl(new MockResponse(
            \substr(self::PAYLOAD, $resumeSize),
            [
                'http_code'        => 206,
                'response_headers' => [
                    'Content-Range: bytes '
                        . $resumeSize
                        . '-'
                        . ( \strlen(self::PAYLOAD) - 1 )
                        . '/'
                        . \strlen(self::PAYLOAD),
                ],
            ],
        ));

        $result = $curl->download(self::URL, $this->destination);

        self::assertSame($this->destination, $result);
        self::assertSame(self::PAYLOAD, \file_get_contents($this->destination));
        self::assertFileDoesNotExist($this->tempFilePath());
    }

    public function testResumePreservesDefaultAuthorizationHeader(): void
    {
        $resumeSize = 10;
        $this->seedTempFile(\substr(self::PAYLOAD, 0, $resumeSize));

        $seen = null;

        $http = new MockHttpClient(
            static function(
                string $method,
                string $url,
                array  $options,
            ) use (&$seen, $resumeSize): MockResponse {
                $seen = $options;

                return new MockResponse(
                    \substr(self::PAYLOAD, $resumeSize),
                    [
                        'http_code'        => 206,
                        'response_headers' => [
                            'Content-Range: bytes '
                                . $resumeSize
                                . '-'
                                . ( \strlen(self::PAYLOAD) - 1 )
                                . '/'
                                . \strlen(self::PAYLOAD),
                        ],
                    ],
                );
            },
        );

        $curl = new CURL(
            defaultOptions: ['headers' => ['Authorization' => 'Bearer secret']],
            cacheDirectory: $this->cacheDir,
            httpClient    : $http,
        );

        $result = $curl->download(self::URL, $this->destination);

        self::assertSame($this->destination, $result);
        self::assertIsArray($seen);
        self::assertArrayHasKey('normalized_headers', $seen);
        self::assertArrayHasKey('authorization', $seen['normalized_headers']);
        self::assertArrayHasKey('range', $seen['normalized_headers']);
        self::assertSame(
            ['Range: bytes=' . $resumeSize . '-'],
            $seen['normalized_headers']['range'],
        );
    }

    public function testResumeRestartsWhenRangeIsIgnored(): void
    {
        $this->seedTempFile(\substr(self::PAYLOAD, 0, 10));

        $curl = $this->curl(new MockResponse(
            self::PAYLOAD,
            [
                'http_code'        => 200,
                'response_headers' => ['Content-Length: ' . \strlen(self::PAYLOAD)],
            ],
        ));

        $result = $curl->download(self::URL, $this->destination);

        self::assertSame($this->destination, $result);
        self::assertSame(self::PAYLOAD, \file_get_contents($this->destination));
    }

    public function testResumeRestartsOnInvalidContentRange(): void
    {
        $resumeSize = 10;
        $this->seedTempFile(\substr(self::PAYLOAD, 0, $resumeSize));

        $curl = $this->curl(
            new MockResponse(
                \substr(self::PAYLOAD, $resumeSize),
                [
                    'http_code'        => 206,
                    'response_headers' => [
                        'Content-Range: bytes 4-' . ( \strlen(self::PAYLOAD) - 1 ) . '/' . \strlen(self::PAYLOAD),
                    ],
                ],
            ),
            new MockResponse(
                self::PAYLOAD,
                [
                    'http_code'        => 200,
                    'response_headers' => ['Content-Length: ' . \strlen(self::PAYLOAD)],
                ],
            ),
        );

        $result = $curl->download(self::URL, $this->destination);

        self::assertSame($this->destination, $result);
        self::assertSame(self::PAYLOAD, \file_get_contents($this->destination));
    }

    public function testResumePromotesWhen416AndTempIsComplete(): void
    {
        $this->seedTempFile(self::PAYLOAD);

        $curl = $this->curl(new MockResponse(
            '',
            [
                'http_code'        => 416,
                'response_headers' => ['Content-Range: bytes */' . \strlen(self::PAYLOAD)],
            ],
        ));

        $result = $curl->download(self::URL, $this->destination);

        self::assertSame($this->destination, $result);
        self::assertSame(self::PAYLOAD, \file_get_contents($this->destination));
    }

    public function testResumeRestartsWhen416AndTempIsIncomplete(): void
    {
        $this->seedTempFile(\substr(self::PAYLOAD, 0, 10));

        $curl = $this->curl(
            new MockResponse(
                '',
                [
                    'http_code'        => 416,
                    'response_headers' => ['Content-Range: bytes */' . \strlen(self::PAYLOAD)],
                ],
            ),
            new MockResponse(
                self::PAYLOAD,
                [
                    'http_code'        => 200,
                    'response_headers' => ['Content-Length: ' . \strlen(self::PAYLOAD)],
                ],
            ),
        );

        $result = $curl->download(self::URL, $this->destination);

        self::assertSame($this->destination, $result);
        self::assertSame(self::PAYLOAD, \file_get_contents($this->destination));
    }

    public function testTransportFailureKeepsValidPrefix(): void
    {
        $resumeSize = 10;
        $this->seedTempFile(\substr(self::PAYLOAD, 0, $resumeSize));

        $curl = $this->curl(new MockResponse(
            [\substr(self::PAYLOAD, $resumeSize, 5), new TransportException('Connection reset')],
            [
                'http_code'        => 206,
                'response_headers' => [
                    'Content-Range: bytes '
                        . $resumeSize
                        . '-'
                        . ( \strlen(self::PAYLOAD) - 1 )
                        . '/'
                        . \strlen(self::PAYLOAD),
                ],
            ],
        ));

        $result = $curl->download(self::URL, $this->destination);

        self::assertFalse($result);
        self::assertFileDoesNotExist($this->destination);

        $temp = \file_get_contents($this->tempFilePath());
        self::assertSame(\substr(self::PAYLOAD, 0, $resumeSize + 5), $temp);
    }

    public function testPromoteFailureKeepsCompletedTempForRetry(): void
    {
        $filesystem                = new FilesystemStub;
        $filesystem->copyFileError = new FilesystemException(
            message: 'copyFile failed',
            path   : $this->destination,
        );

        $curl = new CURL(
            filesystem    : $filesystem,
            cacheDirectory: $this->cacheDir,
            httpClient    : new MockHttpClient([
                new MockResponse(
                    self::PAYLOAD,
                    [
                        'http_code'        => 200,
                        'response_headers' => ['Content-Length: ' . \strlen(self::PAYLOAD)],
                    ],
                ),
            ]),
        );

        $result = $curl->download(self::URL, $this->destination);

        self::assertFalse($result);
        self::assertFileDoesNotExist($this->destination);
        self::assertFileExists($this->tempFilePath());
        self::assertSame(self::PAYLOAD, \file_get_contents($this->tempFilePath()));

        $filesystem->copyFileError = null;

        $retry = new CURL(
            filesystem    : $filesystem,
            cacheDirectory: $this->cacheDir,
            httpClient    : new MockHttpClient([
                new MockResponse(
                    '',
                    [
                        'http_code'        => 416,
                        'response_headers' => ['Content-Range: bytes */' . \strlen(self::PAYLOAD)],
                    ],
                ),
            ]),
        );

        $result = $retry->download(self::URL, $this->destination);

        self::assertSame($this->destination, $result);
        self::assertSame(self::PAYLOAD, \file_get_contents($this->destination));
        self::assertFileDoesNotExist($this->tempFilePath());
    }

    public function testPromoteSucceedsWhenTempCleanupFails(): void
    {
        $filesystem              = new FilesystemStub;
        $filesystem->removeError = new FilesystemException(
            message: 'remove failed',
            path   : $this->tempFilePath(),
        );

        $curl = new CURL(
            filesystem    : $filesystem,
            cacheDirectory: $this->cacheDir,
            httpClient    : new MockHttpClient([
                new MockResponse(
                    self::PAYLOAD,
                    [
                        'http_code'        => 200,
                        'response_headers' => ['Content-Length: ' . \strlen(self::PAYLOAD)],
                    ],
                ),
            ]),
        );

        $result = $curl->download(self::URL, $this->destination);

        self::assertSame($this->destination, $result);
        self::assertSame(self::PAYLOAD, \file_get_contents($this->destination));
        self::assertFileExists($this->tempFilePath());
        self::assertContains('remove', $filesystem->calls);
    }

    public function testFreshDownload(): void
    {
        $curl = $this->curl(new MockResponse(
            self::PAYLOAD,
            [
                'http_code'        => 200,
                'response_headers' => ['Content-Length: ' . \strlen(self::PAYLOAD)],
            ],
        ));

        $result = $curl->download(self::URL, $this->destination);

        self::assertSame($this->destination, $result);
        self::assertSame(self::PAYLOAD, \file_get_contents($this->destination));
        self::assertFileDoesNotExist($this->tempFilePath());
    }

    public function testResolveDownloadPathStripsQueryAndFragment(): void
    {
        $dir = $this->workDir . '/downloads';
        \mkdir($dir, 0777, true);

        $url      = 'https://example.com/path/report.csv?token=secret#section';
        $expected = $dir . '/report.csv';

        $curl = $this->curl(new MockResponse(
            self::PAYLOAD,
            [
                'http_code'        => 200,
                'response_headers' => ['Content-Length: ' . \strlen(self::PAYLOAD)],
            ],
        ));

        $result = $curl->download($url, $dir);

        self::assertSame($expected, $result);
        self::assertFileExists($expected);
        self::assertFileDoesNotExist($dir . '/report.csv?token=secret#section');
    }

    public function testTransportFailureOnFreshDownloadKeepsPrefix(): void
    {
        $curl = $this->curl(new MockResponse(
            [\substr(self::PAYLOAD, 0, 12), new TransportException('Connection reset')],
            ['http_code' => 200],
        ));

        $result = $curl->download(self::URL, $this->destination);

        self::assertFalse($result);
        self::assertFileDoesNotExist($this->destination);
        self::assertSame(\substr(self::PAYLOAD, 0, 12), \file_get_contents($this->tempFilePath()));
    }

    public function testErrorStatusReturnsFalseAndRemovesTemp(): void
    {
        $curl = $this->curl(new MockResponse('server error', ['http_code' => 500]));

        $result = $curl->download(self::URL, $this->destination);

        self::assertFalse($result);
        self::assertFileDoesNotExist($this->destination);
        self::assertFileDoesNotExist($this->tempFilePath());
    }

    public function testLocationWithExtensionIsUsedVerbatim(): void
    {
        $destination = $this->workDir . '/renamed.dat';

        $curl = $this->curl(new MockResponse(
            self::PAYLOAD,
            [
                'http_code'        => 200,
                'response_headers' => ['Content-Length: ' . \strlen(self::PAYLOAD)],
            ],
        ));

        $result = $curl->download(self::URL, $destination);

        self::assertSame($destination, $result);
        self::assertSame(self::PAYLOAD, \file_get_contents($destination));
        self::assertFileDoesNotExist($destination . '/file.bin');
    }

    public function testDownloadToCallableDeliversStreamedContents(): void
    {
        $contents = null;

        $curl = $this->curl(new MockResponse(
            self::PAYLOAD,
            [
                'http_code'        => 200,
                'response_headers' => ['Content-Length: ' . \strlen(self::PAYLOAD)],
            ],
        ));

        $result = $curl->download(self::URL, static function(
            mixed $handle,
        ) use (&$contents): void {
            if (! \is_resource($handle)) {
                throw new \LogicException('Expected download stream resource.');
            }

            $contents = \stream_get_contents($handle);
        });

        self::assertTrue($result);
        self::assertSame(self::PAYLOAD, $contents);
    }

    public function testDownloadToCallableReturnsFalseOnErrorStatus(): void
    {
        $invoked = false;

        $curl = $this->curl(new MockResponse('server error', ['http_code' => 500]));

        $result = $curl->download(self::URL, static function() use (&$invoked): void {
            $invoked = true;
        });

        self::assertFalse($result);
        self::assertFalse($invoked);
    }

    public function testDownloadToCallableReturnsFalseOnRedirectStatus(): void
    {
        $invoked = false;

        $curl = $this->curl(new MockResponse('redirect', ['http_code' => 302]));

        $result = $curl->download(self::URL, static function() use (&$invoked): void {
            $invoked = true;
        });

        self::assertFalse($result);
        self::assertFalse($invoked);
    }

    private function curl(
        MockResponse ...$responses,
    ): CURL {
        return new CURL(
            cacheDirectory: $this->cacheDir,
            httpClient    : new MockHttpClient($responses),
        );
    }

    private function tempFilePath(): string
    {
        return $this->cacheDir . '/' . \hash('xxh32', $this->destination) . '.tmp';
    }

    private function seedTempFile(
        string $contents,
    ): void {
        \mkdir($this->cacheDir, 0777, true);
        \file_put_contents($this->tempFilePath(), $contents);
    }

    private function removeDirectory(
        string $directory,
    ): void {
        if (! \is_dir($directory)) {
            return;
        }

        $entries = \glob($directory . '/*') ?: [];

        foreach ($entries as $entry) {
            \is_dir($entry) ? $this->removeDirectory($entry) : \unlink($entry);
        }

        \rmdir($directory);
    }
}

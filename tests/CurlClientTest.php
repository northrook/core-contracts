<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\CURL;
use Northrook\Contracts\Tests\Support\InspectHttpClient;
use Northrook\Contracts\Tests\Support\RecordingHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CurlClientTest extends TestCase
{
    public function testInjectedClientReceivesDefaultOptions(): void
    {
        $http = new RecordingHttpClient;
        $curl = new CURL(
            defaultOptions: ['timeout' => 5],
            httpClient    : $http,
        );

        $client = $curl->client();

        self::assertNotSame($http, $client);
        self::assertInstanceOf(RecordingHttpClient::class, $client);
        self::assertSame([['timeout' => 5]], $http->withOptionsCalls());
    }

    public function testCallOptionsOverrideDefaultOptions(): void
    {
        $http = new RecordingHttpClient;
        $curl = new CURL(
            defaultOptions: ['timeout' => 5],
            httpClient    : $http,
        );

        $client = $curl->client(['timeout' => 10]);

        self::assertInstanceOf(RecordingHttpClient::class, $client);
        self::assertSame(
            [
                ['timeout' => 5],
                ['timeout' => 10],
            ],
            $http->withOptionsCalls(),
        );
    }

    public function testInjectedClientReturnedAsIsWhenNoOptions(): void
    {
        $http = new RecordingHttpClient;
        $curl = new CURL(httpClient: $http);

        self::assertSame($http, $curl->client());
        self::assertSame([], $http->withOptionsCalls());
    }

    public function testRequestAppliesDefaultOptionsViaWithOptions(): void
    {
        $http = new RecordingHttpClient;
        $curl = new CURL(
            defaultOptions: [
                'timeout' => 5,
                'headers' => ['X-Token' => 'secret'],
            ],
            httpClient    : $http,
        );

        $curl->request('GET', 'https://example.test/path');

        self::assertSame(
            [
                [
                    'timeout' => 5,
                    'headers' => ['X-Token' => 'secret'],
                ],
            ],
            $http->withOptionsCalls(),
        );
        self::assertSame(
            [
                [
                    'method'  => 'GET',
                    'url'     => 'https://example.test/path',
                    'options' => [],
                ],
            ],
            $http->requestCalls(),
        );
    }

    public function testRequestOptionsOverrideDefaultOptions(): void
    {
        $http = new RecordingHttpClient;
        $curl = new CURL(
            defaultOptions: ['timeout' => 5],
            httpClient    : $http,
        );

        $curl->head('https://example.test/path', ['timeout' => 10]);

        self::assertSame(
            [
                ['timeout' => 5],
                ['timeout' => 10],
            ],
            $http->withOptionsCalls(),
        );
        self::assertSame(
            [
                [
                    'method'  => 'HEAD',
                    'url'     => 'https://example.test/path',
                    'options' => [],
                ],
            ],
            $http->requestCalls(),
        );
    }

    public function testNestedHeadersMergeWithoutWipingDefaults(): void
    {
        $http = new RecordingHttpClient;
        $curl = new CURL(
            defaultOptions: ['headers' => ['Authorization' => 'Bearer secret']],
            httpClient    : $http,
        );

        $curl->client(['headers' => ['Range' => 'bytes=10-']]);

        self::assertSame(
            [
                ['headers' => ['Authorization' => 'Bearer secret']],
                ['headers' => ['Range' => 'bytes=10-']],
            ],
            $http->withOptionsCalls(),
        );
    }

    public function testMockClientPreservesDefaultHeadersWhenAddingRange(): void
    {
        $seen = null;

        $http = new MockHttpClient(
            static function(
                string $method,
                string $url,
                array  $options,
            ) use (&$seen): MockResponse {
                $seen = $options;

                return new MockResponse('ok');
            },
        );

        $curl = new CURL(
            defaultOptions: ['headers' => ['Authorization' => 'Bearer secret']],
            httpClient    : $http,
        );

        $curl->client(['headers' => ['Range' => 'bytes=10-']])->request('GET', 'https://example.test/file');

        self::assertIsArray($seen);
        self::assertArrayHasKey('normalized_headers', $seen);
        self::assertArrayHasKey('authorization', $seen['normalized_headers']);
        self::assertArrayHasKey('range', $seen['normalized_headers']);
    }

    public function testInspectAppliesDefaultOptions(): void
    {
        $http = new RecordingHttpClient;
        $curl = new CURL(
            defaultOptions: ['headers' => ['X-Token' => 'secret']],
            httpClient    : $http,
        );

        $meta = $curl->inspect('https://example.test/inspect');

        self::assertSame(200, $meta['status']);
        self::assertSame(
            [
                [
                    'headers' => ['X-Token' => 'secret'],
                ],
                [
                    'timeout'       => 5,
                    'max_redirects' => 20,
                ],
            ],
            $http->withOptionsCalls(),
        );
    }

    public function testInspectCacheIsInstanceLocal(): void
    {
        $url  = 'https://example.test/resource';
        $meta = [
            'status'  => 200,
            'headers' => ['content-type' => ['text/plain']],
            'url'     => $url,
        ];

        $firstHttp  = new InspectHttpClient([$meta, $meta]);
        $secondHttp = new InspectHttpClient([$meta]);
        $first      = new CURL(httpClient: $firstHttp);
        $second     = new CURL(httpClient: $secondHttp);

        self::assertSame(200, $first->inspect($url)['status']);
        self::assertSame(200, $first->inspect($url)['status']);
        self::assertSame(1, $firstHttp->requestCount);

        self::assertSame(200, $second->inspect($url)['status']);
        self::assertSame(1, $secondHttp->requestCount);
    }

    public function testInspectCacheKeysByOptionsFingerprint(): void
    {
        $url  = 'https://example.test/options';
        $meta = [
            'status'  => 200,
            'headers' => ['x-variant' => ['a']],
            'url'     => $url,
        ];
        $other = [
            'status'  => 200,
            'headers' => ['x-variant' => ['b']],
            'url'     => $url,
        ];

        $http = new InspectHttpClient([$meta, $other]);
        $curl = new CURL(httpClient: $http);

        $a      = $curl->inspect($url, options: ['headers' => ['X-Token' => 'a']]);
        $b      = $curl->inspect($url, options: ['headers' => ['X-Token' => 'b']]);
        $aAgain = $curl->inspect($url, options: ['headers' => ['X-Token' => 'a']]);

        self::assertSame(['a'], $a['headers']['x-variant']);
        self::assertSame(['b'], $b['headers']['x-variant']);
        self::assertSame($a, $aAgain);
        self::assertSame(2, $http->requestCount);
    }
}

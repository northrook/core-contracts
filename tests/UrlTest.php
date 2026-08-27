<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\CurlException;
use Northrook\CurlInterface;
use Northrook\DependencyException;
use Northrook\InvalidArgumentException;
use Northrook\Reference\Href;
use Northrook\Reference\Uri;
use Northrook\Reference\Url;
use Northrook\Runtime\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class UrlTest extends TestCase
{
    #[DataProvider('provideAcceptedUrls')]
    public function testAcceptsHttpUrls(
        string $input,
        string $expectedHost,
    ): void {
        $url = new Url($input);

        self::assertSame($expectedHost, $url->host());
        self::assertTrue($url->isAbsolute());
        self::assertTrue(Url::isValid($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideAcceptedUrls(): iterable
    {
        yield 'https' => ['https://example.com/path', 'example.com'];
        yield 'http' => ['http://example.com', 'example.com'];
        yield 'idn' => ['https://exämple.com/päd', 'xn--exmple-cua.com'];
    }

    #[DataProvider('provideRejectedUrls')]
    public function testRejectsNonHttpUrls(
        string $input,
    ): void {
        self::assertFalse(Url::isValid($input));
        self::assertNull(Url::from($input));

        $this->expectException(InvalidArgumentException::class);
        new Url($input);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideRejectedUrls(): iterable
    {
        yield 'relative path' => ['/rel/path'];
        yield 'mailto' => ['mailto:a@b.c'];
        yield 'ftp' => ['ftp://host/x'];
        yield 'file empty host' => ['file:///tmp/x'];
        yield 'drive letter' => ['C:/Windows'];
        yield 'single-char scheme' => ['a:foo'];
        yield 'empty' => [''];
    }

    public function testMailtoValidAsUriNotUrl(): void
    {
        self::assertTrue(Uri::isValid('mailto:a@b.c'));
        self::assertFalse(Url::isValid('mailto:a@b.c'));
    }

    public function testAssertValidUrl(): void
    {
        self::assertTrue(Assert::validUrl('https://example.com'));

        $catch = true;
        self::assertFalse(Assert::validUrl('mailto:a@b.c', catch: $catch));
        self::assertFalse(Assert::validUrl('ftp://host/x', catch: $catch));
        self::assertFalse(Assert::validUrl('C:/Windows', catch: $catch));
        self::assertFalse(Assert::validUrl('/rel', catch: $catch));
    }

    public function testExtendsUri(): void
    {
        self::assertInstanceOf(Uri::class, new Url('https://example.com'));
    }

    public function testWhatWgCanonicalForm(): void
    {
        // WHATWG adds a trailing slash to bare hosts and ASCII-canonicalizes IDN.
        self::assertSame('https://example.com/', new Url('https://example.com')->value);
        self::assertSame(
            'https://xn--exmple-cua.com/p%C3%A4d',
            new Url('https://exämple.com/päd')->value,
        );
    }

    public function testIsSecureAndIsHttp(): void
    {
        self::assertTrue(new Url('https://example.com')->isSecure());
        self::assertTrue(new Url('https://example.com')->isHttp());
        self::assertFalse(new Url('http://example.com')->isSecure());
        self::assertTrue(new Url('http://example.com')->isHttp());
    }

    public function testWithersKeepUrlTypeAndHttpClient(): void
    {
        $curl = $this->createMock(CurlInterface::class);
        $curl->expects(self::once())->method('probeUrl')->willReturn(true);

        $url  = new Url('https://example.com/a', http: $curl);
        $next = $url->withPath('/b');

        self::assertInstanceOf(Url::class, $next);
        self::assertSame('https://example.com/b', $next->value);
        self::assertTrue($next->probe());
    }

    public function testExistsDelegatesToProbe(): void
    {
        $curl = $this->createMock(CurlInterface::class);
        $curl->expects(self::once())->method('probeUrl')->willReturn(true);

        $url = new Url('https://example.com', http: $curl);

        self::assertTrue($url->exists());
    }

    public function testFetchRequiresHttpClient(): void
    {
        $this->expectException(DependencyException::class);
        new Url('https://example.com')->fetch();
    }

    public function testDownloadRequiresHttpClient(): void
    {
        $this->expectException(DependencyException::class);
        new Url('https://example.com/file.txt')->download();
    }

    public function testFetchWrapsUnexpectedThrowables(): void
    {
        $curl = $this->createStub(CurlInterface::class);
        $curl->method('get')->willThrowException(new \Exception('connection reset'));

        $url = new Url('https://example.com', http: $curl);

        $this->expectException(CurlException::class);
        $url->fetch();
    }

    public function testFetchPassesCurlExceptionThrough(): void
    {
        $failure = new CurlException(
            url    : 'https://example.com/',
            message: 'boom',
        );

        $curl = $this->createStub(CurlInterface::class);
        $curl->method('get')->willThrowException($failure);

        $url = new Url('https://example.com', http: $curl);

        try {
            $url->fetch();
            self::fail('Expected CurlException.');
        }
        catch (CurlException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    public function testDownloadFailureThrowsCurlException(): void
    {
        $curl = $this->createStub(CurlInterface::class);
        $curl->method('download')->willReturn(false);

        $url = new Url('https://example.com/file.txt', http: $curl);

        $this->expectException(CurlException::class);
        $url->download(\sys_get_temp_dir() . '/contracts-url-download-fail.txt');
    }

    public function testAsHrefRoundTrip(): void
    {
        $href = new Url('https://example.com/path')->asHref();

        self::assertInstanceOf(Href::class, $href);
        self::assertSame('https://example.com/path', $href->value);
        self::assertTrue($href->is(Href::Https));
    }

    public function testProbeRequiresHttpClient(): void
    {
        $url = new Url('https://example.com');

        $this->expectException(DependencyException::class);
        $url->probe();
    }

    public function testProbeDelegatesToHttpClient(): void
    {
        $curl = $this->createMock(CurlInterface::class);
        $curl->expects(self::once())->method('probeUrl')->with('https://example.com/', false, true, [])->willReturn(true);

        $url = new Url('https://example.com', http: $curl);

        self::assertTrue($url->probe());
    }

    public function testFetchDelegatesToHttpClient(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getContent')->willReturn('body');

        $curl = $this->createMock(CurlInterface::class);
        $curl->expects(self::once())->method('get')->with('https://example.com/', [], [])->willReturn($response);

        $url = new Url('https://example.com', http: $curl);

        self::assertSame('body', $url->fetch());
    }

    public function testDownloadDelegatesToHttpClient(): void
    {
        $path = \sys_get_temp_dir() . '/contracts-url-download-test.txt';
        @\unlink($path);

        $curl = $this->createMock(CurlInterface::class);
        $curl
            ->expects(self::once())
            ->method('download')
            ->willReturnCallback(static function(
                string $url,
                string $location,
            ): string {
                \file_put_contents($location, 'content');

                return $location;
            });

        $url = new Url('https://example.com/file.txt', http: $curl);

        $file = $url->download($path);

        self::assertSame($path, (string) $file);
        self::assertSame('content', \file_get_contents($path));
        @\unlink($path);
    }

    public function testDownloadUsesResolvedPathReturnedByHttpClient(): void
    {
        $location = \sys_get_temp_dir() . '/contracts-url-download-resolved';
        $resolved = $location . '/file.txt';
        @\unlink($resolved);

        $curl = $this->createMock(CurlInterface::class);
        $curl
            ->expects(self::once())
            ->method('download')
            ->willReturnCallback(static function(
                string $url,
                string $location,
            ) use ($resolved): string {
                if (! \is_dir($location)) {
                    \mkdir($location, 0777, true);
                }

                \file_put_contents($resolved, 'content');

                return $resolved;
            });

        $url = new Url('https://example.com/file.txt', http: $curl);

        $file = $url->download($location);

        self::assertSame($resolved, (string) $file);
        self::assertSame('content', \file_get_contents($resolved));
        @\unlink($resolved);
        @\rmdir($location);
    }
}

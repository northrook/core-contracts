<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Href;
use Northrook\InvalidArgumentException;
use Northrook\Runtime\Assert;
use Northrook\RuntimeException;
use Northrook\Uri;
use Northrook\Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UriTest extends TestCase
{
    public function testAcceptsOpaqueAndRelativeForms(): void
    {
        self::assertSame('mailto:a@b.c', new Uri('mailto:a@b.c')->value);
        self::assertSame('file:///tmp/x', new Uri('file:///tmp/x')->value);
        self::assertSame('/rel/path', new Uri('/rel/path')->value);
        self::assertTrue(new Uri('/rel/path')->isRelative());
        self::assertSame('a:foo', new Uri('a:foo')->value);
    }

    public function testRejectsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Uri('');
    }

    public function testComponents(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/p?q=1#f');

        self::assertSame('https', $uri->scheme());
        self::assertSame('user:pass', $uri->userInfo());
        self::assertSame('example.com', $uri->host());
        self::assertSame(8080, $uri->port());
        self::assertSame('/p', $uri->path());
        self::assertSame('q=1', $uri->query());
        self::assertSame('f', $uri->fragment());
    }

    public function testAbsentComponentsAreNull(): void
    {
        $uri = new Uri('/relative/path');

        self::assertNull($uri->scheme());
        self::assertNull($uri->userInfo());
        self::assertNull($uri->host());
        self::assertNull($uri->port());
        self::assertNull($uri->query());
        self::assertNull($uri->fragment());
    }

    public function testSchemeAndHostAreLowercased(): void
    {
        self::assertSame('https://example.com/Path', new Uri('HTTPS://EXAMPLE.COM/Path')->value);
    }

    public function testQueryParams(): void
    {
        $uri = new Uri('https://example.com/x?a=1&a=2&b&c=b%20c');

        self::assertSame(['a' => ['1', '2'], 'b' => '', 'c' => 'b c'], $uri->queryParams());
        self::assertSame([], new Uri('https://example.com/x')->queryParams());
    }

    public function testPredicates(): void
    {
        self::assertTrue(new Uri('https://example.com')->isAbsolute());
        self::assertFalse(new Uri('https://example.com')->isRelative());
        self::assertTrue(new Uri('/rel')->isRelative());
        self::assertTrue(new Uri('https://example.com')->isHttp());
        self::assertTrue(new Uri('http://example.com')->isHttp());
        self::assertTrue(new Uri('https://example.com')->isSecure());
        self::assertFalse(new Uri('http://example.com')->isSecure());
        self::assertTrue(new Uri('mailto:a@b.c')->isMailto());
        self::assertTrue(new Uri('file:///tmp/x')->isFile());
        self::assertFalse(new Uri('https://example.com')->isMailto());
    }

    public function testAppend(): void
    {
        $uri = new Uri('https://example.com/a');

        self::assertSame('https://example.com/a/b', $uri->append('b')->value);
        self::assertSame('https://example.com/a/b/c', $uri->append('/b/c')->value);
        self::assertSame('https://example.com/b', new Uri('https://example.com')->append('b')->value);
        self::assertSame($uri, $uri->append(''));
    }

    public function testWithers(): void
    {
        $uri = new Uri('https://user@example.com:8080/p?q=1#f');

        self::assertSame('http://user@example.com:8080/p?q=1#f', $uri->withScheme('http')->value);
        self::assertSame('//user@example.com:8080/p?q=1#f', $uri->withScheme(null)->value);
        self::assertSame('https://user@example.org:8080/p?q=1#f', $uri->withHost('example.org')->value);
        self::assertSame('https://user@example.com:9090/p?q=1#f', $uri->withPort(9090)->value);
        self::assertSame('https://example.com:8080/p?q=1#f', $uri->withUserInfo(null)->value);
        self::assertSame('https://user@example.com:8080/p?q=1', $uri->withFragment(null)->value);
        self::assertSame('https://user@example.com:8080/p?q=1', $uri->withFragment('')->value);
    }

    public function testWithHostRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Uri('https://example.com')->withHost('');
    }

    public function testWithQueryFromArray(): void
    {
        $uri = new Uri('https://example.com/x');

        self::assertSame(
            'https://example.com/x?a=b%20c&n=&b=1&l=1&l=2',
            $uri->withQuery(['a' => 'b c', 'n' => null, 'b' => true, 'l' => [1, 2]])->value,
        );
        self::assertSame('https://example.com/x?z=9', $uri->withQuery('?z=9')->value);
        self::assertSame('https://example.com/x', $uri->withQuery(null)->value);
        self::assertSame('https://example.com/x', $uri->withQuery('')->value);
    }

    public function testWithQueryRejectsInvalidMaps(): void
    {
        $uri = new Uri('https://example.com/x');

        try {
            // @phpstan-ignore-next-line Testing invalid input.
            $uri->withQuery([0 => 'x']);
            self::fail('Expected InvalidArgumentException for non-string keys.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $uri->withQuery(['x' => new \stdClass]);
    }

    public function testQueryParamHelpers(): void
    {
        $uri = new Uri('https://example.com/x?a=1&b=2');

        self::assertSame('https://example.com/x?a=1&b=2&c=3', $uri->withQueryParam('c', '3')->value);
        self::assertSame('https://example.com/x?a=1&b=9', $uri->withQueryParam('b', '9')->value);
        self::assertSame('https://example.com/x?b=2', $uri->withoutQueryParam('a')->value);
        self::assertSame('https://example.com/x', $uri->withoutQueryParam('a')->withoutQueryParam('b')->value);
    }

    public function testMergeQuery(): void
    {
        $uri = new Uri('https://example.com/x?y=1');

        self::assertSame('https://example.com/x?y=2&z=3', $uri->mergeQuery(['y' => 2, 'z' => 3])->value);
    }

    public function testResolve(): void
    {
        $base = new Uri('https://example.com/a/b');

        self::assertSame('https://example.com/a/c', $base->resolve('c')->value);
        self::assertSame('https://example.com/d', $base->resolve('../d')->value);
        self::assertSame('https://other.example/x', $base->resolve('https://other.example/x')->value);
    }

    public function testConstructorResolvesAgainstBase(): void
    {
        $uri = new Uri('rel/path', new Uri('https://example.com/a/'));

        self::assertSame('https://example.com/a/rel/path', $uri->value);
    }

    public function testEquals(): void
    {
        $one = new Uri('https://example.com/a#f1');
        $two = new Uri('https://example.com/a#f2');

        // Fragments are excluded by default.
        self::assertTrue($one->equals($two));
        self::assertFalse($one->equals($two, includeFragment: true));
        self::assertTrue($one->equals(new Uri('https://example.com/a#f1'), true));
    }

    public function testToPath(): void
    {
        $path = new Uri('file:///tmp/x')->toPath();

        self::assertSame('/tmp/x', $path->value);
    }

    public function testToPathThrowsForNonFileScheme(): void
    {
        $this->expectException(RuntimeException::class);
        new Uri('https://example.com/x')->toPath();
    }

    public function testMailtoPathIsOpaque(): void
    {
        self::assertSame('a@b.c', new Uri('mailto:a@b.c')->path());
    }

    public function testHasNoTransportApi(): void
    {
        self::assertFalse(\method_exists(Uri::class, 'probe'));
        self::assertFalse(\method_exists(Uri::class, 'fetch'));
        self::assertFalse(\method_exists(Uri::class, 'download'));
        self::assertFalse(\method_exists(Uri::class, 'isFetchable'));
    }

    public function testWitherFromUrlStaysUrl(): void
    {
        $url  = new Url('https://example.com/a');
        $next = $url->withPath('/b');

        self::assertInstanceOf(Url::class, $next);
        self::assertSame('https://example.com/b', $next->value);
    }

    #[DataProvider('provideAssertUriDefaults')]
    public function testAssertValidUriDefaults(
        string $value,
        bool   $expected,
    ): void {
        $catch = true;
        $ok    = Assert::validUri($value, catch: $catch);

        self::assertSame($expected, $ok);
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function provideAssertUriDefaults(): iterable
    {
        yield 'https' => ['https://example.com', true];
        yield 'mailto' => ['mailto:a@b.c', true];
        yield 'relative rejected' => ['/assets/app.css', false];
        yield 'single-char rejected' => ['C:/Windows', false];
        yield 'empty' => ['', false];
    }

    public function testAssertValidUriAllowRelativeAndSingleChar(): void
    {
        self::assertTrue(Assert::validUri('/assets/app.css', allowRelative: true));
        self::assertTrue(Assert::validUri('C:/Windows', allowSingleCharScheme: true));
    }

    public function testAsUrlPromotesHttpUri(): void
    {
        $url = new Uri('https://example.com/a')->asUrl();

        self::assertInstanceOf(Url::class, $url);
        self::assertSame('https://example.com/a', $url->value);
    }

    public function testAsUrlThrowsForNonHttpScheme(): void
    {
        $this->expectException(RuntimeException::class);
        new Uri('mailto:a@b.c')->asUrl();
    }

    public function testAsHrefPromotesUri(): void
    {
        $href = new Uri('mailto:a@b.c')->asHref();

        self::assertInstanceOf(Href::class, $href);
        self::assertSame('mailto:a@b.c', $href->value);
        self::assertTrue($href->isMailto());
    }
}

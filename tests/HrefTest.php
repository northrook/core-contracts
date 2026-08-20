<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\InvalidArgumentException;
use Northrook\Reference\Href;
use Northrook\Reference\Uri;
use Northrook\Reference\Url;
use Northrook\Runtime\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HrefTest extends TestCase
{
    #[DataProvider('provideAcceptedHrefs')]
    public function testAcceptsSafeHrefs(
        string $input,
        string $expectedType,
    ): void {
        $href = new Href($input);

        self::assertSame($input, $href->value);
        self::assertSame($expectedType, $href->type);
        self::assertTrue(Href::isValid($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideAcceptedHrefs(): iterable
    {
        yield 'relative' => ['/assets/app.css', Href::Relative];
        yield 'fragment' => ['#section', Href::Fragment];
        yield 'query' => ['?page=2', Href::Query];
        yield 'protocol-relative' => ['//cdn.example.com/x', Href::ProtocolRelative];
        yield 'https' => ['https://example.com', Href::Https];
        yield 'http' => ['http://example.com', Href::Http];
        yield 'mailto' => ['mailto:user@example.com', Href::Mailto];
        yield 'tel' => ['tel:+441234567890', Href::Tel];
        yield 'sms' => ['sms:+441234567890', Href::Sms];
    }

    #[DataProvider('provideRejectedHrefs')]
    public function testRejectsUnsafeHrefs(
        string $input,
    ): void {
        self::assertFalse(Href::isValid($input));
        self::assertNull(Href::from($input));

        $this->expectException(InvalidArgumentException::class);
        new Href($input);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideRejectedHrefs(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'data' => ['data:text/plain,hi'];
        yield 'ftp' => ['ftp://host/x'];
        yield 'empty' => [''];
    }

    public function testPredicates(): void
    {
        self::assertTrue(new Href('mailto:a@b.c')->isMailto());
        self::assertTrue(new Href('tel:+123')->isTel());
        self::assertTrue(new Href('/x')->isRelative());
        self::assertTrue(new Href('#x')->isAnchor());
        self::assertTrue(new Href('https://example.com')->isExternal());
        self::assertFalse(new Href('/x')->isExternal());
        self::assertTrue(new Href('//cdn.example.com/x')->isProtocolRelative());
        self::assertTrue(new Href('sms:+123')->isSms());
        self::assertTrue(new Href('http://example.com')->isHttp());
        self::assertTrue(new Href('https://example.com')->isHttp());
        self::assertFalse(new Href('mailto:a@b.c')->isHttp());
        self::assertTrue(new Href('/x')->is(Href::Relative));
        self::assertFalse(new Href('/x')->is(Href::Https));
    }

    public function testScheme(): void
    {
        self::assertSame('https', new Href('https://example.com')->scheme());
        self::assertSame('mailto', new Href('mailto:a@b.c')->scheme());
        self::assertSame('tel', new Href('tel:+123')->scheme());
        self::assertNull(new Href('/x')->scheme());
        self::assertNull(new Href('#x')->scheme());
        self::assertNull(new Href('?q=1')->scheme());
        self::assertNull(new Href('//cdn.example.com/x')->scheme());
    }

    public function testEquals(): void
    {
        $href = new Href('/x');

        self::assertTrue($href->equals(new Href('/x')));
        self::assertFalse($href->equals(new Href('/y')));
    }

    public function testRejectsControlCharacters(): void
    {
        self::assertFalse(Href::isValid("/x\ny"));

        $this->expectException(InvalidArgumentException::class);
        new Href("/x\ty");
    }

    public function testRejectsSingleCharScheme(): void
    {
        self::assertFalse(Href::isValid('a:foo'));

        $this->expectException(InvalidArgumentException::class);
        new Href('a:foo');
    }

    public function testRejectsUnknownScheme(): void
    {
        self::assertFalse(Href::isValid('ssh://host'));
        self::assertNull(Href::from('ssh://host'));
    }

    public function testIsExternalRequiresHost(): void
    {
        self::assertTrue(new Href('https://example.com/x')->isExternal());
        self::assertFalse(new Href('mailto:a@b.c')->isExternal());
        self::assertFalse(new Href('//cdn.example.com/x')->isExternal());
    }

    #[DataProvider('provideSchemeCaseNormalization')]
    public function testLowercasesOpaqueSchemes(
        string $input,
        string $expected,
        string $expectedType,
    ): void {
        $href = new Href($input);

        self::assertSame($expected, $href->value);
        self::assertSame($expectedType, $href->type);
        self::assertSame($expected, Href::normalize($input));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function provideSchemeCaseNormalization(): iterable
    {
        yield 'mailto' => ['MAILTO:user@example.com', 'mailto:user@example.com', Href::Mailto];
        yield 'tel' => ['TEL:+441234567890', 'tel:+441234567890', Href::Tel];
        yield 'sms' => ['SMS:+441234567890', 'sms:+441234567890', Href::Sms];
        yield 'mixed mailto' => ['MailTo:a@b.c', 'mailto:a@b.c', Href::Mailto];
    }

    public function testEmailAndPhoneExtractors(): void
    {
        self::assertSame('user@example.com', new Href('mailto:user@example.com')->email());
        self::assertSame('+441234567890', new Href('tel:+44 123 456 7890')->phone());
        self::assertSame(
            '+12015550123',
            new Href('tel:+1-201-555-0123;phone-context=+1-555')->phone(),
        );
        self::assertSame('+12015550123', new Href('sms:+1-201-555-0123;ext=123')->phone());
        self::assertNull(new Href('/x')->email());
        self::assertNull(new Href('/x')->phone());
        self::assertNull(new Href('mailto:a@b.c')->phone());
        self::assertSame('a@b.c', new Href('mailto:a@b.c,d@e.f?subject=x')->email());
        self::assertNull(new Href('mailto:')->email());
        self::assertSame(
            'müller@example.com',
            new Href('mailto:m%C3%BCller@example.com')->email(),
        );
    }

    public function testAsUrlPromotesHttpHref(): void
    {
        $url = new Href('https://example.com')->asUrl();

        self::assertInstanceOf(Url::class, $url);
    }

    public function testAsUrlThrowsForRelativeHref(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Href('/dashboard')->asUrl();
    }

    public function testToUriRoundTrip(): void
    {
        $uri = new Href('mailto:a@b.c')->toUri();

        self::assertInstanceOf(Uri::class, $uri);
        self::assertSame('mailto:a@b.c', $uri->value);
    }

    public function testAssertValidHref(): void
    {
        self::assertTrue(Assert::validHref('/assets/app.css'));

        $catch = true;
        self::assertFalse(Assert::validHref('javascript:void(0)', catch: $catch));
        self::assertFalse(Assert::validHref('', catch: $catch));
    }

    public function testHasNoTransportApi(): void
    {
        self::assertFalse(\method_exists(Href::class, 'probe'));
        self::assertFalse(\method_exists(Href::class, 'fetch'));
        self::assertFalse(\method_exists(Href::class, 'download'));
    }
}

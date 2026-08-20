<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\InvalidArgumentException;
use Northrook\Reference\Href;
use Northrook\Reference\Mailto;
use PHPUnit\Framework\TestCase;

final class MailtoTest extends TestCase
{
    public function testBuildsFromParts(): void
    {
        $mailto = new Mailto('user@example.com', subject: 'Hello', body: 'World');

        self::assertTrue($mailto->isMailto());
        self::assertSame(['user@example.com'], $mailto->recipients());
        self::assertSame('Hello', $mailto->subject());
        self::assertSame('World', $mailto->body());
        self::assertStringStartsWith('mailto:user@example.com?', (string) $mailto);
        self::assertStringContainsString('subject=Hello', (string) $mailto);
        self::assertStringContainsString('body=World', (string) $mailto);
    }

    public function testParsesExistingMailto(): void
    {
        $mailto = Mailto::parse('mailto:user@example.com?subject=Hi&body=There');

        self::assertSame(['user@example.com'], $mailto->recipients());
        self::assertSame('Hi', $mailto->subject());
        self::assertSame('There', $mailto->body());
    }

    public function testMultipleRecipients(): void
    {
        $mailto = new Mailto(['a@b.c', 'b@d.e']);

        self::assertSame(['a@b.c', 'b@d.e'], $mailto->recipients());
        self::assertSame('mailto:a@b.c,b@d.e', $mailto->value);
    }

    public function testWithersReturnNewInstances(): void
    {
        $original = new Mailto('a@b.c');
        $next     = $original->withRecipient('b@d.e')->withSubject('Hi')->withBody('Body');

        self::assertNotSame($original, $next);
        self::assertSame(['a@b.c'], $original->recipients());
        self::assertSame(['a@b.c', 'b@d.e'], $next->recipients());
        self::assertSame('Hi', $next->subject());
        self::assertSame('Body', $next->body());
    }

    public function testRejectsEmptyRecipients(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Mailto([]);
    }

    public function testRejectsInvalidRecipient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Mailto('not-an-email');
    }

    public function testNormalizesRecipientDomain(): void
    {
        $mailto = new Mailto('  User@Example.COM  ');

        self::assertSame(['User@example.com'], $mailto->recipients());
        self::assertSame('mailto:User@example.com', $mailto->value);
    }

    public function testQuotedRecipientRoundTrip(): void
    {
        $mailto = new Mailto('"Doe, John"@example.com');

        self::assertSame(['"Doe, John"@example.com'], $mailto->recipients());
        self::assertSame('mailto:%22Doe%2C%20John%22@example.com', $mailto->value);
    }

    public function testUtf8RecipientIsEncoded(): void
    {
        $mailto = new Mailto('müller@example.com');

        self::assertSame(['müller@example.com'], $mailto->recipients());
        self::assertSame('müller@example.com', $mailto->email());
        self::assertStringStartsWith('mailto:m%C3%BCller@example.com', $mailto->value);
    }

    public function testAtextMailtoDelimitersAreEncoded(): void
    {
        $mailto = new Mailto('user?name@example.com', subject: 'Hi there');

        self::assertSame(['user?name@example.com'], $mailto->recipients());
        self::assertSame('Hi there', $mailto->subject());
        self::assertSame(
            'mailto:user%3Fname@example.com?subject=Hi%20there',
            $mailto->value,
        );

        $withAmp = new Mailto('a&b=c@example.com');
        self::assertSame(['a&b=c@example.com'], $withAmp->recipients());
        self::assertSame('mailto:a%26b%3Dc@example.com', $withAmp->value);
    }

    public function testPercentInRecipientIsEncoded(): void
    {
        // Bare `%40` in a mailto href would be URI-decoded to `@` by parsers.
        $mailto = new Mailto('foo%40bar@example.com');

        self::assertSame(['foo%40bar@example.com'], $mailto->recipients());
        self::assertSame('mailto:foo%2540bar@example.com', $mailto->value);

        $parsed = Mailto::parse($mailto->value);
        self::assertSame(['foo%40bar@example.com'], $parsed->recipients());
        self::assertSame($mailto->value, $parsed->value);
    }

    public function testParseRejectsNonMailto(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Mailto::parse('https://example.com');
    }

    public function testExtendsHref(): void
    {
        self::assertInstanceOf(Href::class, new Mailto('a@b.c'));
    }

    public function testReferenceAlignsForBareEmail(): void
    {
        $input = 'User@Example.COM';

        self::assertTrue(Mailto::isValid($input));
        self::assertSame('mailto:User@example.com', Mailto::normalize($input));

        $from = Mailto::from($input);
        self::assertInstanceOf(Mailto::class, $from);
        self::assertSame('mailto:User@example.com', $from->value);
    }

    public function testReferenceAlignsForMailtoHref(): void
    {
        $input = 'mailto:user@example.com?subject=Hi';

        self::assertTrue(Mailto::isValid($input));
        self::assertSame('mailto:user@example.com?subject=Hi', Mailto::normalize($input));

        $from = Mailto::from($input);
        self::assertInstanceOf(Mailto::class, $from);
        self::assertSame(['user@example.com'], $from->recipients());
        self::assertSame('Hi', $from->subject());
    }

    public function testReferenceRejectsNonMailtoHrefs(): void
    {
        self::assertFalse(Mailto::isValid('https://example.com'));
        self::assertFalse(Mailto::isValid('/relative/path'));
        self::assertNull(Mailto::from('https://example.com'));
        self::assertNull(Mailto::from('/relative/path'));
    }

    public function testNormalizeRejectsInvalidMailtoRecipient(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Mailto::normalize('mailto:not-an-email');
    }

    public function testFromThrowOnMailtoHref(): void
    {
        $mailto = Mailto::from('mailto:a@b.c', throw: true);

        self::assertInstanceOf(Mailto::class, $mailto);
        self::assertSame('mailto:a@b.c', $mailto->value);
    }

    public function testFromSoftFailOnJunk(): void
    {
        self::assertNull(Mailto::from('not-an-email'));
        self::assertNull(Mailto::from(42));
    }

    public function testFromThrowOnJunk(): void
    {
        $this->expectException(\Northrook\RuntimeException::class);
        Mailto::from('not-an-email', throw: true);
    }

    public function testFromAcceptsStringable(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'user@example.com';
            }
        };

        $mailto = Mailto::from($stringable);

        self::assertInstanceOf(Mailto::class, $mailto);
        self::assertSame('mailto:user@example.com', $mailto->value);
    }

    public function testTypeIsMailto(): void
    {
        $mailto = new Mailto('a@b.c');

        self::assertSame(Href::Mailto, $mailto->type);
        self::assertTrue($mailto->isMailto());
        self::assertSame('mailto:a@b.c', (string) $mailto);
    }

    public function testWithSubjectNullClearsSubject(): void
    {
        $mailto = new Mailto('a@b.c', subject: 'Hi', body: 'There')->withSubject(null);

        self::assertNull($mailto->subject());
        self::assertSame('There', $mailto->body());
        self::assertSame('mailto:a@b.c?body=There', $mailto->value);
    }

    public function testWithBodyNullClearsBody(): void
    {
        $mailto = new Mailto('a@b.c', subject: 'Hi', body: 'There')->withBody(null);

        self::assertSame('Hi', $mailto->subject());
        self::assertNull($mailto->body());
        self::assertSame('mailto:a@b.c?subject=Hi', $mailto->value);
    }

    public function testParseWithoutQuery(): void
    {
        $mailto = Mailto::parse('mailto:a@b.c');

        self::assertSame(['a@b.c'], $mailto->recipients());
        self::assertNull($mailto->subject());
        self::assertNull($mailto->body());
    }

    public function testParseDecodesEncodedHfields(): void
    {
        $mailto = Mailto::parse('mailto:user%3Fname@example.com?subject=Hi%20there');

        self::assertSame(['user?name@example.com'], $mailto->recipients());
        self::assertSame('Hi there', $mailto->subject());
    }

    public function testNormalizeIsIdempotent(): void
    {
        $once = Mailto::normalize('User@Example.COM');

        self::assertSame($once, Mailto::normalize($once));
        self::assertSame('mailto:User@example.com', $once);
    }

    public function testValueRoundTripsThroughParse(): void
    {
        $built  = new Mailto(['a@b.c', 'b@d.e'], subject: 'Hi there', body: 'Line one');
        $parsed = Mailto::parse($built->value);

        self::assertSame($built->recipients(), $parsed->recipients());
        self::assertSame($built->subject(), $parsed->subject());
        self::assertSame($built->body(), $parsed->body());
        self::assertSame($built->value, $parsed->value);
    }
}

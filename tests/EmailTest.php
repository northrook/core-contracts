<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Email;
use Northrook\Contracts\EmailIssue;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Tests\Support\MixedArray;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Valid addresses
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function provideValidAddresses(): array
    {
        return [
            'plain'          => ['user@example.com', 'user', 'example.com'],
            'plus tag'       => ['user+tag@example.com', 'user+tag', 'example.com'],
            'atext symbols'  => ["!#$%&'*+-/=?^_`{|}~@example.com", "!#$%&'*+-/=?^_`{|}~", 'example.com'],
            'case preserved' => ['User@Example.COM', 'User', 'example.com'],
            'single label'   => ['user@localhost', 'user', 'localhost'],
            'short labels'   => ['a@b.c', 'a', 'b.c'],
            'ipv4 literal'   => ['user@[192.168.0.1]', 'user', '[192.168.0.1]'],
            'ipv6 literal'   => ['user@[IPv6:2001:DB8::1]', 'user', '[ipv6:2001:db8::1]'],
            'quoted simple'  => ['"quoted string"@example.com', '"quoted string"', 'example.com'],
            'quoted unusual' => [
                '"very.unusual.@.unusual.com"@example.com',
                '"very.unusual.@.unusual.com"',
                'example.com',
            ],
            'quoted escaped' => ['"a\\"b"@example.com', '"a\\"b"', 'example.com'],
            'eai local'      => ['müller@example.com', 'müller', 'example.com'],
            'idn domain'     => ['user@münchen.de', 'user', 'xn--mnchen-3ya.de'],
        ];
    }

    #[DataProvider('provideValidAddresses')]
    public function testValidAddresses(
        string $input,
        string $local,
        string $domain,
    ): void {
        $email = new Email($input);

        self::assertSame($local, $email->local);
        self::assertSame($domain, $email->domain);
        self::assertSame($local . '@' . $domain, $email->value);
        self::assertSame($email->value, (string) $email);
        self::assertTrue(Email::isValid($input));
        self::assertSame([], Email::issues($input));
    }

    public function testIsQuoted(): void
    {
        self::assertTrue(new Email('"quoted"@example.com')->isQuoted());
        self::assertFalse(new Email('plain@example.com')->isQuoted());
    }

    public function testQuotedLocalWithAtSign(): void
    {
        $email = new Email('"a@b"@example.com');

        self::assertSame('"a@b"', $email->local);
        self::assertSame('example.com', $email->domain);
        self::assertTrue($email->isQuoted());
        self::assertSame([], Email::issues('"a@b"@example.com'));
    }

    public function testAcceptsStringable(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'User@Example.COM';
            }
        };

        $email = new Email($stringable);

        self::assertSame('User@example.com', $email->value);
        self::assertSame('User@example.com', Email::normalize($stringable));
        self::assertInstanceOf(Email::class, Email::from($stringable));
    }

    public function testEveryIssueHasAMessage(): void
    {
        foreach (EmailIssue::cases() as $issue) {
            self::assertNotSame('', $issue->message(), $issue->name . ' has an empty message.');
        }
    }

    public function testFailureExceptionShape(): void
    {
        try {
            new Email('not-an-email');
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringStartsWith('Invalid email address', $exception->getMessage());
            self::assertArrayHasKey('issues', $exception->context);
            self::assertContains(EmailIssue::MissingAt->value, MixedArray::at($exception->context, 'issues'));
        }
    }

    public function testIsDomainLiteral(): void
    {
        self::assertTrue(new Email('user@[192.168.0.1]')->isDomainLiteral());
        self::assertFalse(new Email('user@example.com')->isDomainLiteral());
    }

    public function testIsSingleLabelDomain(): void
    {
        self::assertTrue(new Email('user@localhost')->isSingleLabelDomain());
        self::assertFalse(new Email('user@example.com')->isSingleLabelDomain());
        self::assertFalse(new Email('user@[192.168.0.1]')->isSingleLabelDomain());
    }

    // -----------------------------------------------------------------------
    // Invalid addresses
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{string, EmailIssue}>
     */
    public static function provideInvalidAddresses(): array
    {
        return [
            'empty'              => ['', EmailIssue::Empty],
            'whitespace only'    => ['   ', EmailIssue::Empty],
            'missing at'         => ['user', EmailIssue::MissingAt],
            'unterminated quote' => ['"unterminated@example.com', EmailIssue::MissingAt],
            'multiple at'        => ['user@@example.com', EmailIssue::MultipleAt],
            'local empty'        => ['@example.com', EmailIssue::LocalEmpty],
            'leading dot'        => ['.first@example.com', EmailIssue::LocalLeadingDot],
            'trailing dot'       => ['first.@example.com', EmailIssue::LocalTrailingDot],
            'consecutive dots'   => ['first..last@example.com', EmailIssue::LocalConsecutiveDots],
            'space in local'     => ['user name@example.com', EmailIssue::LocalInvalidCharacter],
            'quote inside atom'  => ['us"er@example.com', EmailIssue::LocalInvalidCharacter],
            'premature quote'    => ['"a"b@example.com', EmailIssue::QuotedUnterminated],
            'domain empty'       => ['user@', EmailIssue::DomainEmpty],
            'empty label'        => ['user@example..com', EmailIssue::DomainEmptyLabel],
            'leading hyphen'     => ['user@-example.com', EmailIssue::DomainHyphenEdge],
            'trailing hyphen'    => ['user@example-.com', EmailIssue::DomainHyphenEdge],
            'domain space'       => ['user@exa mple.com', EmailIssue::DomainInvalidCharacter],
            'domain underscore'  => ['user@ex_ample.com', EmailIssue::DomainInvalidCharacter],
            'bad ipv4 literal'   => ['user@[999.1.1.1]', EmailIssue::DomainInvalidLiteral],
            'bad ipv6 literal'   => ['user@[IPv6:xyz]', EmailIssue::DomainInvalidLiteral],
            'unclosed literal'   => ['user@[192.168.0.1', EmailIssue::DomainInvalidLiteral],
            'control character'  => ["user\n@example.com", EmailIssue::ControlCharacter],
            'invalid utf-8'      => ["user@\xc3\x28.com", EmailIssue::InvalidUtf8],
        ];
    }

    #[DataProvider('provideInvalidAddresses')]
    public function testInvalidAddresses(
        string     $input,
        EmailIssue $issue,
    ): void {
        self::assertContains($issue, Email::issues($input));
        self::assertFalse(Email::isValid($input));

        try {
            new Email($input);
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertIsArray($exception->context['issues']);
            self::assertContains($issue->value, $exception->context['issues']);
            self::assertStringContainsString($issue->message(), $exception->getMessage());
        }
    }

    public function testLocalTooLong(): void
    {
        $input = \str_repeat('a', 65) . '@example.com';

        self::assertContains(EmailIssue::LocalTooLong, Email::issues($input));
    }

    public function testAddressTooLong(): void
    {
        $input = \str_repeat('a', 250) . '@x.co';

        self::assertContains(EmailIssue::AddressTooLong, Email::issues($input));
        self::assertContains(EmailIssue::LocalTooLong, Email::issues($input));
    }

    public function testDomainLabelTooLong(): void
    {
        $input = 'a@' . \str_repeat('b', 64) . '.com';

        self::assertContains(EmailIssue::DomainLabelTooLong, Email::issues($input));
    }

    public function testDomainTooLongUsesWireForm(): void
    {
        // 4×63-char labels + ".com" = 259 > MAX_DOMAIN (255); each label still ≤ 63.
        $domain =
            \str_repeat('a', 63)
            . '.'
            . \str_repeat('b', 63)
            . '.'
            . \str_repeat('c', 63)
            . '.'
            . \str_repeat('d', 63)
            . '.com';

        self::assertGreaterThan(255, \strlen($domain));
        self::assertContains(EmailIssue::DomainTooLong, Email::issues('user@' . $domain));
    }

    public function testDomainLengthMeasuredAfterPunycode(): void
    {
        // 31× single CJK labels: UTF-8 well under 255, wire form approaches the limit.
        $domain = \implode('.', \array_fill(0, 31, '中'));
        $ascii  = \idn_to_ascii($domain, \IDNA_DEFAULT, \INTL_IDNA_VARIANT_UTS46);

        self::assertNotFalse($ascii);
        self::assertLessThanOrEqual(255, \strlen((string) $ascii));
        self::assertSame([], Email::issues('user@' . $domain));
    }

    public function testMultipleIssuesCollected(): void
    {
        $issues = Email::issues('.a..b@example..com');

        self::assertContains(EmailIssue::LocalLeadingDot, $issues);
        self::assertContains(EmailIssue::LocalConsecutiveDots, $issues);
        self::assertContains(EmailIssue::DomainEmptyLabel, $issues);
    }

    // -----------------------------------------------------------------------
    // Configuration
    // -----------------------------------------------------------------------

    public function testQuotedRejectedWhenNotAllowed(): void
    {
        $issues = Email::issues('"quoted"@example.com', allowQuoted: false);

        self::assertContains(EmailIssue::QuotedNotAllowed, $issues);
    }

    public function testPolicyRejection(): void
    {
        $allowlist = static fn(string $local, string $domain): null|string => $domain === 'example.com'
            ? null
            : "Domain '{$domain}' is not allowed.";

        self::assertSame([], Email::issues('user@example.com', policy: $allowlist));

        try {
            new Email('user@other.com', policy: $allowlist);
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertIsArray($exception->context['issues']);
            self::assertContains(EmailIssue::PolicyRejected->value, $exception->context['issues']);
            self::assertSame("Domain 'other.com' is not allowed.", $exception->context['policy']);
            self::assertStringContainsString("Domain 'other.com' is not allowed.", $exception->getMessage());
        }
    }

    public function testPolicyReceivesNormalizedParts(): void
    {
        $received = [];
        $capture  = static function(
            string $local,
            string $domain,
        ) use (&$received): null {
            $received = [$local, $domain];

            return null;
        };

        new Email('User@München.de', policy: $capture);

        self::assertSame(['User', 'xn--mnchen-3ya.de'], $received);
    }

    public function testDnsCheckSkipsDomainLiterals(): void
    {
        // No resolver is queried for literals — offline-safe.
        self::assertInstanceOf(Email::class, new Email('user@[127.0.0.1]', dnsCheck: true));
    }

    // -----------------------------------------------------------------------
    // Reference contract
    // -----------------------------------------------------------------------

    public function testNormalize(): void
    {
        self::assertSame('User@example.com', Email::normalize('  User@Example.COM  '));
    }

    public function testFrom(): void
    {
        self::assertInstanceOf(Email::class, Email::from('user@example.com'));
        self::assertNull(Email::from('not-an-email'));
        self::assertNull(Email::from(123));
    }

    public function testFromThrow(): void
    {
        $this->expectException(RuntimeException::class);
        Email::from('not-an-email', true);
    }
}

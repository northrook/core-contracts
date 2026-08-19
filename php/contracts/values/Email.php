<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Reference;
use Northrook\Email\EmailIssue;

/**
 * Email address {@see Reference} — RFC 5322 `addr-spec` with RFC 5321 limits.
 *
 * Deterministic byte-walking scanner — no regex grammar, no external parser.
 * Covers dot-atom and quoted-string local-parts, dot-atom domains with DNS
 * label rules (RFC 1035/1123), and IPv4/IPv6 domain-literals. Comments (CFWS),
 * folding whitespace, and obsolete forms are rejected, per RFC 5322 guidance.
 *
 * Internationalized addresses (EAI, RFC 6531) are accepted: UTF-8 local-parts
 * verbatim, IDN domains normalized to punycode via UTS #46 (`ext-intl`).
 *
 * Normalization: trims, lowercases the domain, punycodes IDN domains; the
 * local-part is preserved verbatim (RFC 5321: case-sensitive).
 *
 * Diagnostics: failures throw {@see InvalidArgumentException} with
 * `context['issues']` = `list<EmailIssue>`; {@see issues()} is the
 * non-throwing equivalent.
 *
 * DNS (MX/A/AAAA) verification is opt-in via `$dnsCheck` — it performs live
 * network queries and is skipped for domain-literals.
 */
final readonly class Email implements Reference
{
    use ReferenceTrait;

    /** Visible ASCII valid in dot-atom atoms (RFC 5322 §3.2.3 atext). */
    private const string ATEXT = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!#$%&'*+-/=?^_`{|}~";

    /** Valid DNS label characters (RFC 1123). */
    private const string LABEL = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-';

    private const int MAX_ADDRESS = 254;
    private const int MAX_LOCAL   = 64;
    private const int MAX_DOMAIN  = 255;
    private const int MAX_LABEL   = 63;

    /**
     * Canonical address (`local@domain`) after {@see normalize()}.
     *
     * @var non-empty-string
     */
    public string $value;

    /** Local-part, verbatim (case preserved, quotes retained when quoted). */
    public string $local;

    /** Domain, lowercased; punycode when internationalized; `[…]` when a literal. */
    public string $domain;

    private bool $quoted;

    /**
     * @param string|\Stringable                       $value       Email address to validate and normalize
     * @param bool                                     $allowQuoted Accept quoted-string local-parts
     * @param null|callable(string, string): ?string   $policy      `fn( string $local, string $domain ): ?string`
     *                                                              — null passes, string rejects with that reason
     * @param bool                                     $dnsCheck    Verify MX/A/AAAA records via live DNS queries
     *
     * @throws InvalidArgumentException When `$value` is not a valid email address
     */
    public function __construct(
        string|\Stringable $value,
        bool               $allowQuoted = true,
        null|callable      $policy = null,
        bool               $dnsCheck = false,
    ) {
        $result = self::parse($value, $allowQuoted, $policy, $dnsCheck);

        if ($result['issues'] !== []) {
            self::fail($result['received'], $result['issues'], $result['policy']);
        }

        $this->local  = $result['local'];
        $this->domain = $result['domain'];
        $this->quoted = $result['quoted'];

        $this->value = $this->local . '@' . $this->domain;
    }

    /**
     * Canonical address string for this reference type.
     *
     * {@inheritDoc}
     *
     * @param bool                                   $allowQuoted Accept quoted-string local-parts
     * @param null|callable(string, string): ?string $policy      Rejection callback; string return = reason
     * @param bool                                   $dnsCheck    Verify MX/A/AAAA records via live DNS queries
     *
     * @return non-empty-string
     */
    public static function normalize(
        string|\Stringable $value,
        bool               $allowQuoted = true,
        null|callable      $policy = null,
        bool               $dnsCheck = false,
    ): string {
        $result = self::parse($value, $allowQuoted, $policy, $dnsCheck);

        if ($result['issues'] !== []) {
            self::fail($result['received'], $result['issues'], $result['policy']);
        }

        return $result['local'] . '@' . $result['domain'];
    }

    /**
     * Validation issues for `$value`, non-throwing. Empty list = valid.
     *
     * @param null|callable(string, string): ?string $policy
     *
     * @return list<EmailIssue>
     */
    public static function issues(
        string|\Stringable $value,
        bool               $allowQuoted = true,
        null|callable      $policy = null,
        bool               $dnsCheck = false,
    ): array {
        return self::parse($value, $allowQuoted, $policy, $dnsCheck)['issues'];
    }

    /**
     * Whether the local-part is a quoted-string (e.g. `"Doe, John"@example.com`).
     */
    public function isQuoted(): bool
    {
        return $this->quoted;
    }

    /**
     * Whether the domain is a literal IP address (`[192.168.0.1]`, `[IPv6:…]`).
     */
    public function isDomainLiteral(): bool
    {
        return \str_starts_with($this->domain, '[');
    }

    /**
     * Whether the domain has a single label (`localhost`, `com`) — RFC-valid,
     * but practically undeliverable on the public internet.
     */
    public function isSingleLabelDomain(): bool
    {
        return ! $this->isDomainLiteral() && ! \str_contains($this->domain, '.');
    }

    /**
     * Validate and split an address into its normalized parts, collecting issues.
     *
     * Policy and DNS checks only run on structurally valid addresses.
     *
     * @param null|callable(string, string): ?string $policy
     *
     * @return array{received: string, local: string, domain: string, quoted: bool, issues: list<EmailIssue>, policy: null|string}
     */
    private static function parse(
        string|\Stringable $value,
        bool               $allowQuoted,
        null|callable      $policy,
        bool               $dnsCheck,
    ): array {
        $string = \trim((string) $value);

        $issues       = [];
        $policyReason = null;
        $local        = '';
        $domain       = '';
        $quoted       = false;

        if ($string === '') {
            $issues[] = EmailIssue::Empty;
        } else {
            if (\strlen($string) > self::MAX_ADDRESS) {
                $issues[] = EmailIssue::AddressTooLong;
            }

            if (\preg_match('/[\x00-\x1F\x7F]/', $string) === 1) {
                $issues[] = EmailIssue::ControlCharacter;
            } elseif (\preg_match('/[\x80-\xFF]/', $string) === 1 && \preg_match('//u', $string) !== 1) {
                $issues[] = EmailIssue::InvalidUtf8;
            } else {
                $at = self::separatorOffset($string);

                if ($at === null) {
                    $issues[] = EmailIssue::MissingAt;
                } else {
                    $local  = \substr($string, 0, $at);
                    $domain = \substr($string, $at + 1);

                    if (\str_contains($domain, '@')) {
                        $issues[] = EmailIssue::MultipleAt;
                    } else {
                        $quoted = self::validateLocal($local, $allowQuoted, $issues);
                        $domain = self::validateDomain($domain, $issues);
                    }
                }
            }
        }

        if ($issues === [] && $policy !== null) {
            $reason = $policy($local, $domain);

            if (\is_string($reason) && $reason !== '') {
                $issues[]     = EmailIssue::PolicyRejected;
                $policyReason = $reason;
            }
        }

        if ($issues === [] && $dnsCheck && ! \str_starts_with($domain, '[') && ! self::acceptsMail($domain)) {
            $issues[] = EmailIssue::DnsNoMailRecord;
        }

        return [
            'received' => $string,
            'local'    => $local,
            'domain'   => $domain,
            'quoted'   => $quoted,
            'issues'   => $issues,
            'policy'   => $policyReason,
        ];
    }

    /**
     * Offset of the `@` separating local-part from domain — the first `@`
     * outside a quoted-string, or null when absent.
     */
    private static function separatorOffset(
        string $string,
    ): null|int {
        $inQuote = false;
        $escaped = false;
        $length  = \strlen($string);

        for ($i = 0; $i < $length; $i++) {
            $char = $string[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($inQuote && $char === '\\') {
                $escaped = true;
                continue;
            }

            // A quoted-string local-part must open at offset 0; a bare quote
            // elsewhere is invalid atext, not a quote delimiter.
            if ($char === '"' && ( $i === 0 || $inQuote )) {
                $inQuote = ! $inQuote;
                continue;
            }

            if ($char === '@' && ! $inQuote) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<EmailIssue> $issues
     *
     * @returns bool Whether the local-part is a quoted-string
     */
    private static function validateLocal(
        string $local,
        bool   $allowQuoted,
        array &$issues,
    ): bool {
        if ($local === '') {
            $issues[] = EmailIssue::LocalEmpty;

            return false;
        }

        if (\strlen($local) > self::MAX_LOCAL) {
            $issues[] = EmailIssue::LocalTooLong;
        }

        if ($local[0] === '"') {
            if (! $allowQuoted) {
                $issues[] = EmailIssue::QuotedNotAllowed;
            }

            self::validateQuotedLocal($local, $issues);

            return true;
        }

        if (\str_starts_with($local, '.')) {
            $issues[] = EmailIssue::LocalLeadingDot;
        }

        if (\str_ends_with($local, '.')) {
            $issues[] = EmailIssue::LocalTrailingDot;
        }

        if (\str_contains($local, '..')) {
            $issues[] = EmailIssue::LocalConsecutiveDots;
        }

        $length = \strlen($local);

        for ($i = 0; $i < $length; $i++) {
            $ord = \ord($local[$i]);

            if ($ord >= 0x80) {
                continue; // EAI (RFC 6531) — UTF-8 validity checked on the full address
            }

            if ($local[$i] === '.') {
                continue; // atom separator — placement checked above
            }

            if (! \str_contains(self::ATEXT, $local[$i])) {
                $issues[] = EmailIssue::LocalInvalidCharacter;
                break;
            }
        }

        return false;
    }

    /**
     * @param list<EmailIssue> $issues
     */
    private static function validateQuotedLocal(
        string $local,
        array &$issues,
    ): void {
        $length = \strlen($local);

        if ($length < 2 || $local[$length - 1] !== '"') {
            $issues[] = EmailIssue::QuotedUnterminated;

            return;
        }

        for ($i = 1; $i < ( $length - 1 ); $i++) {
            $char = $local[$i];

            if ($char === '"') {
                $issues[] = EmailIssue::QuotedUnterminated; // bare quote closes the string prematurely
                return;
            }

            if ($char !== '\\') {
                continue;
            }

            $i++;

            if ($i >= ( $length - 1 ) || \ord($local[$i]) < 32) {
                $issues[] = EmailIssue::QuotedInvalidEscape;
                return;
            }
        }
    }

    /**
     * Validate the domain; returns its normalized form (lowercase, punycode).
     *
     * @param list<EmailIssue> $issues
     */
    private static function validateDomain(
        string $domain,
        array &$issues,
    ): string {
        if ($domain === '') {
            $issues[] = EmailIssue::DomainEmpty;

            return $domain;
        }

        $domain = \strtolower($domain);

        if (\str_starts_with($domain, '[')) {
            self::validateDomainLiteral($domain, $issues);

            if (\strlen($domain) > self::MAX_DOMAIN) {
                $issues[] = EmailIssue::DomainTooLong;
            }

            return $domain;
        }

        if (\preg_match('/[\x80-\xFF]/', $domain) === 1) {
            $ascii = \idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if ($ascii === false) {
                $issues[] = EmailIssue::DomainInvalidIdn;

                return $domain;
            }

            $domain = $ascii;
        }

        // RFC 5321 domain length is on the wire form (ASCII / punycode), not UTF-8.
        if (\strlen($domain) > self::MAX_DOMAIN) {
            $issues[] = EmailIssue::DomainTooLong;
        }

        $invalidCharacter = false;

        foreach (\explode('.', $domain) as $label) {
            if ($label === '') {
                $issues[] = EmailIssue::DomainEmptyLabel;
                continue;
            }

            if (\strlen($label) > self::MAX_LABEL) {
                $issues[] = EmailIssue::DomainLabelTooLong;
            }

            if (\str_starts_with($label, '-') || \str_ends_with($label, '-')) {
                $issues[] = EmailIssue::DomainHyphenEdge;
            }

            if (! $invalidCharacter && \strspn($label, self::LABEL) !== \strlen($label)) {
                $issues[]         = EmailIssue::DomainInvalidCharacter;
                $invalidCharacter = true;
            }
        }

        return $domain;
    }

    /**
     * @param list<EmailIssue> $issues
     */
    private static function validateDomainLiteral(
        string $domain,
        array &$issues,
    ): void {
        if (! \str_ends_with($domain, ']')) {
            $issues[] = EmailIssue::DomainInvalidLiteral;

            return;
        }

        $inner = \substr($domain, 1, -1);

        $valid = \str_starts_with($inner, 'ipv6:')
            ? \filter_var(\substr($inner, 5), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            : \filter_var($inner, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

        if (! $valid) {
            $issues[] = EmailIssue::DomainInvalidLiteral;
        }
    }

    /**
     * Live DNS probe — MX, with A/AAAA fallback (implicit MX, RFC 5321 §5.1).
     */
    private static function acceptsMail(
        string $domain,
    ): bool {
        return \checkdnsrr($domain, 'MX') || \checkdnsrr($domain, 'A') || \checkdnsrr($domain, 'AAAA');
    }

    /**
     * @param list<EmailIssue> $issues
     *
     * @throws InvalidArgumentException
     */
    private static function fail(
        string      $received,
        array       $issues,
        null|string $policyReason = null,
    ): never {
        $messages = \array_map(
            static fn(EmailIssue $issue): string => $issue->message(),
            $issues,
        );

        if ($policyReason !== null) {
            $messages[] = "Policy: {$policyReason}";
        }

        throw new InvalidArgumentException(
            message: 'Invalid email address — ' . \implode(' ', $messages),
            context: \array_filter([
                'issues'   => \array_map(
                    static fn(EmailIssue $issue): string => $issue->value,
                    $issues,
                ),
                'policy'   => $policyReason,
                'name'     => 'email',
                'expected' => 'valid RFC 5322 addr-spec',
                'received' => $received,
            ]),
        );
    }
}

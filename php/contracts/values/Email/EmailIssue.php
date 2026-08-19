<?php

declare(strict_types=1);

namespace Northrook\Email;

use Northrook\Email;

/**
 * Diagnostic cases produced by {@see Email} validation.
 *
 * Every rejected address carries one or more cases (exception `context['issues']`,
 * or {@see Email::issues()} non-throwing), so failures can be surfaced to users
 * or logs with accurate, per-case feedback.
 */
enum EmailIssue: string
{
    case Empty                  = 'empty';
    case AddressTooLong         = 'address_too_long';
    case ControlCharacter       = 'control_character';
    case InvalidUtf8            = 'invalid_utf8';
    case MissingAt              = 'missing_at';
    case MultipleAt             = 'multiple_at';
    case LocalEmpty             = 'local_empty';
    case LocalTooLong           = 'local_too_long';
    case LocalLeadingDot        = 'local_leading_dot';
    case LocalTrailingDot       = 'local_trailing_dot';
    case LocalConsecutiveDots   = 'local_consecutive_dots';
    case LocalInvalidCharacter  = 'local_invalid_character';
    case QuotedUnterminated     = 'quoted_unterminated';
    case QuotedInvalidEscape    = 'quoted_invalid_escape';
    case QuotedNotAllowed       = 'quoted_not_allowed';
    case DomainEmpty            = 'domain_empty';
    case DomainTooLong          = 'domain_too_long';
    case DomainEmptyLabel       = 'domain_empty_label';
    case DomainLabelTooLong     = 'domain_label_too_long';
    case DomainHyphenEdge       = 'domain_hyphen_edge';
    case DomainInvalidCharacter = 'domain_invalid_character';
    case DomainInvalidLiteral   = 'domain_invalid_literal';
    case DomainInvalidIdn       = 'domain_invalid_idn';
    case PolicyRejected         = 'policy_rejected';
    case DnsNoMailRecord        = 'dns_no_mail_record';

    /**
     * Human-readable explanation of this issue.
     */
    public function message(): string
    {
        return match ($this) {
            self::Empty => 'The address is empty.',
            self::AddressTooLong => 'The address exceeds 254 octets (RFC 5321).',
            self::ControlCharacter => 'The address contains control characters.',
            self::InvalidUtf8 => 'The address contains invalid UTF-8 byte sequences.',
            self::MissingAt => 'The address has no @ separator.',
            self::MultipleAt => 'The domain contains an @ character.',
            self::LocalEmpty => 'The local-part is empty.',
            self::LocalTooLong => 'The local-part exceeds 64 octets (RFC 5321).',
            self::LocalLeadingDot => 'The local-part starts with a dot.',
            self::LocalTrailingDot => 'The local-part ends with a dot.',
            self::LocalConsecutiveDots => 'The local-part contains consecutive dots.',
            self::LocalInvalidCharacter => 'The local-part contains characters outside the atext set.',
            self::QuotedUnterminated => 'The quoted-string local-part is unterminated or closed prematurely.',
            self::QuotedInvalidEscape => 'The quoted-string local-part contains an invalid escape sequence.',
            self::QuotedNotAllowed => 'Quoted-string local-parts are not accepted by this configuration.',
            self::DomainEmpty => 'The domain is empty.',
            self::DomainTooLong => 'The domain exceeds 255 octets (RFC 5321).',
            self::DomainEmptyLabel => 'The domain contains an empty label (leading, trailing, or consecutive dots).',
            self::DomainLabelTooLong => 'A domain label exceeds 63 octets (RFC 1035).',
            self::DomainHyphenEdge => 'A domain label starts or ends with a hyphen (RFC 1123).',
            self::DomainInvalidCharacter => 'The domain contains characters outside alnum and hyphen.',
            self::DomainInvalidLiteral => 'The domain-literal is not a valid IPv4 or IPv6 address.',
            self::DomainInvalidIdn => 'The internationalized domain cannot be converted to punycode (UTS #46).',
            self::PolicyRejected => 'The address was rejected by the configured policy.',
            self::DnsNoMailRecord => 'The domain has no MX, A, or AAAA record.',
        };
    }
}

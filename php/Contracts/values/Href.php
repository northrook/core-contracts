<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Uri\InvalidUriException;

/**
 * HTML link target {@see Reference}.
 *
 * Opinionated carrier for `href`, `src`, and `action` attribute values.
 * Separate from {@see Uri} — no host/port/path plumbing; use {@see Url} or
 * {@see Uri} when structural manipulation or transport is needed.
 *
 * Rejects scriptable schemes (`javascript:`, `data:`). Allows relative paths,
 * fragments, query-only references, protocol-relative URLs, and a fixed set of
 * absolute schemes ({@see ALLOWED_SCHEMES}).
 */
readonly class Href implements Reference
{
    use ReferenceTrait;

    public const string Fragment         = 'fragment';
    public const string Query            = 'query';
    public const string Relative         = 'relative';
    public const string ProtocolRelative = 'protocol-relative';
    public const string Http             = 'http';
    public const string Https            = 'https';
    public const string Mailto           = 'mailto';
    public const string Tel              = 'tel';
    public const string Sms              = 'sms';

    /** @var list<non-empty-lowercase-string> */
    private const array ALLOWED_SCHEMES = [
        'http',
        'https',
        'mailto',
        'tel',
        'sms',
    ];

    /** @var list<non-empty-lowercase-string> */
    private const array DENIED_SCHEMES = [
        'javascript',
        'data',
    ];

    /**
     * Full attribute value after {@see normalize()}.
     *
     * @var non-empty-string
     */
    public string $value;

    /**
     * Classified link type for this href.
     *
     * @var self::Fragment|self::Query|self::Relative|self::ProtocolRelative|self::Http|self::Https|self::Mailto|self::Tel|self::Sms
     */
    public string $type;

    /**
     * @param string|\Stringable $value Attribute value (`/path`, `#frag`, `mailto:…`, …)
     *
     * @throws InvalidArgumentException When `$value` is not a safe href for this type
     */
    public function __construct(
        string|\Stringable $value,
    ) {
        // self:: — not static:: — so Href subclasses (Mailto) can override
        // normalize() for the Reference API without re-entering via parent::__construct().
        $this->value = self::normalize($value);
        $this->type  = self::classify($this->value);
    }

    /**
     * Canonical href string for HTML attributes.
     *
     * {@inheritDoc}
     *
     * @return non-empty-string
     */
    public static function normalize(
        string|\Stringable $value,
    ): string {
        $string = (string) $value;

        if ($string === '') {
            throw new InvalidArgumentException(
                message : 'Href cannot be empty.',
                name    : 'href',
                expected: 'non-empty href',
                received: $value,
            );
        }

        if (\preg_match('/[\x00-\x1F\x7F]/', $string) === 1) {
            throw new InvalidArgumentException(
                message : 'Href cannot contain control characters.',
                name    : 'href',
                expected: 'href without control characters',
                received: $value,
            );
        }

        if (\str_starts_with($string, '//')) {
            return $string;
        }

        if (\str_starts_with($string, '#') || \str_starts_with($string, '?')) {
            return $string;
        }

        if (! \str_contains($string, ':')) {
            return $string;
        }

        $schemeEnd = \strpos($string, ':');

        if ($schemeEnd === false) {
            return $string;
        }

        $scheme = \strtolower(\substr($string, 0, $schemeEnd));

        if (\in_array($scheme, self::DENIED_SCHEMES, true)) {
            throw new InvalidArgumentException(
                message : "Scheme '{$scheme}' is not allowed in href attributes.",
                name    : 'href',
                expected: 'safe href scheme',
                received: $value,
            );
        }

        if (\strlen($scheme) < 2) {
            throw new InvalidArgumentException(
                message : 'Single-character schemes are rejected.',
                name    : 'href',
                expected: 'href with scheme length >= 2 or no scheme',
                received: $value,
            );
        }

        if (! \in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new InvalidArgumentException(
                message : "Scheme '{$scheme}' is not an allowed href scheme.",
                name    : 'href',
                expected: 'allowed href scheme (' . \implode(', ', self::ALLOWED_SCHEMES) . ')',
                received: $value,
            );
        }

        if (\in_array($scheme, ['mailto', 'tel', 'sms'], true)) {
            return $scheme . \substr($string, $schemeEnd);
        }

        try {
            $normalized = new \Uri\Rfc3986\Uri($string)->toString();
        } catch (InvalidUriException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                name    : 'href',
                expected: 'valid href URI',
                received: $value,
                previous: $exception,
            );
        }

        if ($normalized === '') {
            throw new InvalidArgumentException(
                message : 'Href normalized to an empty string.',
                name    : 'href',
                expected: 'non-empty href',
                received: $value,
            );
        }

        return $normalized;
    }

    /**
     * @return self::Fragment|self::Query|self::Relative|self::ProtocolRelative|self::Http|self::Https|self::Mailto|self::Tel|self::Sms
     */
    private static function classify(
        string $value,
    ): string {
        if (\str_starts_with($value, '#')) {
            return self::Fragment;
        }

        if (\str_starts_with($value, '?')) {
            return self::Query;
        }

        if (\str_starts_with($value, '//')) {
            return self::ProtocolRelative;
        }

        if (! \str_contains($value, ':')) {
            return self::Relative;
        }

        $scheme = \strtolower((string) \explode(':', $value, 2)[0]);

        return match ($scheme) {
            'http'   => self::Http,
            'https'  => self::Https,
            'mailto' => self::Mailto,
            'tel'    => self::Tel,
            'sms'    => self::Sms,
            default  => self::Relative,
        };
    }

    /**
     * Whether this href is of the given {@see TYPES} constant.
     */
    public function is(
        string $type,
    ): bool {
        return $this->type === $type;
    }

    public function isRelative(): bool
    {
        return $this->type === self::Relative;
    }

    public function isAnchor(): bool
    {
        return $this->type === self::Fragment;
    }

    public function isProtocolRelative(): bool
    {
        return $this->type === self::ProtocolRelative;
    }

    public function isMailto(): bool
    {
        return $this->type === self::Mailto;
    }

    public function isTel(): bool
    {
        return $this->type === self::Tel;
    }

    public function isSms(): bool
    {
        return $this->type === self::Sms;
    }

    public function isHttp(): bool
    {
        return $this->type === self::Http || $this->type === self::Https;
    }

    /**
     * Absolute http(s) URL with a host (suitable for off-site link treatment).
     */
    public function isExternal(): bool
    {
        if (! $this->isHttp()) {
            return false;
        }

        try {
            $host = new \Uri\Rfc3986\Uri($this->value)->getHost();
        } catch (InvalidUriException) {
            return false;
        }

        return $host !== null && $host !== '';
    }

    /**
     * Scheme component, or null for relative/fragment/query forms.
     */
    public function scheme(): null|string
    {
        if (
            $this->isRelative()
            || $this->isAnchor()
            || $this->isProtocolRelative()
            || \str_starts_with($this->value, '?')
        ) {
            return null;
        }

        return \strtolower((string) \explode(':', $this->value, 2)[0]);
    }

    /**
     * Email address from a `mailto:` href, or null when not applicable.
     */
    public function email(): null|string
    {
        if (! $this->isMailto()) {
            return null;
        }

        $payload = \substr($this->value, 7);
        $address = \explode('?', $payload, 2)[0];

        if ($address === '') {
            return null;
        }

        $recipients = \explode(',', $address);
        $first      = \rawurldecode(\trim($recipients[0]));

        return $first !== '' ? $first : null;
    }

    /**
     * Phone number from a `tel:` or `sms:` href (digits and leading `+` retained).
     */
    public function phone(): null|string
    {
        if (! $this->isTel() && ! $this->isSms()) {
            return null;
        }

        $scheme = $this->scheme();

        if ($scheme === null) {
            return null;
        }

        $payload = \explode(':', $this->value, 2)[1] ?? '';

        if ($payload === '') {
            return null;
        }

        // RFC 3966 uses `;param=value`; strip before digit filtering.
        $number     = \explode(';', $payload, 2)[0];
        $number     = \explode('?', $number, 2)[0];
        $normalized = \preg_replace('/[^\d+]/', '', $number);

        return $normalized !== '' && $normalized !== null ? $normalized : null;
    }

    /**
     * Structural equality of the canonical href string.
     */
    public function equals(
        self $other,
    ): bool {
        return $this->value === $other->value;
    }

    /**
     * Promote to RFC 3986 {@see Uri} plumbing.
     */
    public function toUri(): Uri
    {
        return new Uri($this->value);
    }

    /**
     * Promote to an http(s) {@see Url} for transport helpers.
     *
     * @throws InvalidArgumentException When this href is not http(s)
     */
    public function asUrl(
        null|CurlInterface $http = null,
    ): Url {
        return new Url(
            uri : $this->value,
            http: $http,
        );
    }
}

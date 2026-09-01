<?php

declare(strict_types=1);

namespace Northrook\Reference;

use Northrook\InvalidArgumentException;
use Northrook\RuntimeException;

/**
 * Composable `mailto:` {@see Href}.
 *
 * Dual construction:
 * - **Ctor** — build from recipient(s) and optional subject/body
 *   (`new Mailto('a@b.c', subject: 'Hi')`). Pass addresses, not a `mailto:` href.
 * - **{@see from()} / {@see parse()}** — accept a `mailto:` href string, or
 *   (for {@see from()} / {@see normalize()}) a bare single email address.
 *
 * Immutable withers return new instances.
 */
final readonly class Mailto extends Href
{
    /**
     * @param string|string[] $recipients One or more email addresses; each is
     *                                        validated and normalized via {@see Email}
     *
     * @throws InvalidArgumentException When no recipients are provided, addresses are
     *                                  empty, or a recipient fails {@see Email} validation
     */
    public function __construct(
        string|array $recipients,
        null|string  $subject = null,
        null|string  $body = null,
    ) {
        $list = ( \is_array($recipients) ? $recipients : [$recipients] )
            |> ( static fn(array $values) => \array_map(
                callback: static fn(string $value): string => \trim($value),
                array   : $values,
            ) )
            |> ( static fn(array $trimmed) => \array_filter(
                array   : $trimmed,
                callback: static fn(string $value): bool => $value !== '',
            ) );

        if ($list === []) {
            throw new InvalidArgumentException(
                message: 'Mailto requires at least one recipient.',
                context: [
                    'name'     => 'recipients',
                    'expected' => 'non-empty recipient list',
                    'received' => $recipients,
                ],
            );
        }

        try {
            $list = \array_map(static fn(string $r): string => Email::normalize($r), $list);
        }
        catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException(
                message : $exception->getMessage(),
                context : [
                    'name'     => 'recipients',
                    'expected' => 'valid email address(es)',
                    'received' => $recipients,
                    ...$exception->getContext(),
                ],
                previous: $exception,
            );
        }

        parent::__construct(self::buildMailto($list, $subject, $body));
    }

    /**
     * Canonical `mailto:` href for a bare email or existing mailto string.
     *
     * {@inheritDoc}
     *
     * @return non-empty-string
     */
    public static function normalize(
        string|\Stringable $value,
    ): string {
        $string = \string($value);

        if ($string === '') {
            throw new InvalidArgumentException(
                message: 'Mailto cannot be empty.',
                context: [
                    'name'     => 'value',
                    'expected' => 'bare email or mailto href',
                    'received' => $value,
                ],
            );
        }

        if (self::isMailtoHref($string)) {
            return self::parse($string)->value;
        }

        return new self($string)->value;
    }

    /**
     * Build from a bare email or `mailto:` href, or `null` when invalid.
     *
     * {@inheritDoc}
     */
    public static function from(
        mixed $value,
        bool  $throw = false,
    ): null|static {
        if (! \is_string($value) && ! $value instanceof \Stringable) {
            if ($throw) {
                throw new InvalidArgumentException(
                    context: [
                        'name'     => 'value',
                        'expected' => 'string|Stringable',
                        'received' => $value,
                    ],
                );
            }

            return null;
        }

        $string = \string($value);

        try {
            if (self::isMailtoHref($string)) {
                return self::parse($string);
            }

            return new self($string);
        }
        catch (\Throwable $exception) {
            if ($throw) {
                throw new RuntimeException(
                    message : self::class . ' failed to initialize via from(): ' . $exception->getMessage(),
                    previous: $exception,
                );
            }

            return null;
        }
    }

    /**
     * Parse an existing `mailto:` href string.
     *
     * @throws InvalidArgumentException When `$value` is not a mailto href
     */
    public static function parse(
        string|\Stringable $value,
    ): static {
        $href = new Href($value);

        if (! $href->isMailto()) {
            throw new InvalidArgumentException(
                message: 'Value is not a mailto href.',
                context: [
                    'name'     => 'value',
                    'expected' => 'mailto href',
                    'received' => $value,
                ],
            );
        }

        return new self(
            recipients: self::parseRecipients($href->value),
            subject   : self::parseQueryParam($href->value, 'subject'),
            body      : self::parseQueryParam($href->value, 'body'),
        );
    }

    /**
     * @return string[]
     */
    public function recipients(): array
    {
        return self::parseRecipients($this->value);
    }

    public function subject(): null|string
    {
        return self::parseQueryParam($this->value, 'subject');
    }

    public function body(): null|string
    {
        return self::parseQueryParam($this->value, 'body');
    }

    /**
     * @param string  ...$addresses
     *
     * @return static
     */
    public function withRecipient(
        string ...$addresses,
    ): static {
        return new static(
            recipients: \array_values(\array_merge($this->recipients(), $addresses)),
            subject   : $this->subject(),
            body      : $this->body(),
        );
    }

    /**
     * @param null|string  $subject
     *
     * @return static
     */
    public function withSubject(
        null|string $subject,
    ): static {
        return new static(
            recipients: $this->recipients(),
            subject   : $subject,
            body      : $this->body(),
        );
    }

    /**
     * @param null|string  $body
     *
     * @return static
     */
    public function withBody(
        null|string $body,
    ): static {
        return new static(
            recipients: $this->recipients(),
            subject   : $this->subject(),
            body      : $body,
        );
    }

    private static function isMailtoHref(
        string $value,
    ): bool {
        return \str_starts_with(\strtolower($value), 'mailto:');
    }

    /**
     * @param string[] $recipients
     *
     * @return non-empty-string
     */
    private static function buildMailto(
        array       $recipients,
        null|string $subject,
        null|string $body,
    ): string {
        $mailto = 'mailto:' . \implode(',', \array_map(self::encodeRecipient(...), $recipients));

        $params = [];

        if ($subject !== null && $subject !== '') {
            $params['subject'] = $subject;
        }

        if ($body !== null && $body !== '') {
            $params['body'] = $body;
        }

        if ($params === []) {
            return $mailto;
        }

        $pairs = [];

        foreach ($params as $key => $value) {
            $pairs[] = \rawurlencode($key) . '=' . \rawurlencode($value);
        }

        return $mailto . '?' . \implode('&', $pairs);
    }

    /**
     * Percent-encode a recipient when it is not href-safe (quoted-string
     * local-parts, UTF-8, embedded commas). Local-part and domain are encoded
     * separately so the `@` separator stays readable; the domain is already
     * ASCII after {@see Email} normalization.
     *
     * Mailto-structural characters (`?`, `&`, `=`) are never left bare — they
     * are valid in RFC 5322 atext but delimit hfields in a `mailto:` URI.
     *
     * `%` is also encoded: leaving it bare lets URI parsers treat `%XX` in the
     * local-part as percent-decoding (e.g. `%40` → `@`), diverging from the
     * address {@see Email} validated.
     */
    private static function encodeRecipient(
        string $address,
    ): string {
        // Intentionally omits ? & = (mailto hfield delimiters) and % (URI decode).
        $safe = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!#$'*+-./^_`{|}~@";

        if (\strspn($address, $safe) === \strlen($address)) {
            return $address;
        }

        $at = \strrpos($address, '@');

        if ($at === false) {
            return \rawurlencode($address);
        }

        return \rawurlencode(\substr($address, 0, $at)) . '@' . \rawurlencode(\substr($address, $at + 1));
    }

    /**
     * @return string[]
     */
    private static function parseRecipients(
        string $mailto,
    ): array {
        $payload = \substr($mailto, 7);
        $address = \explode('?', $payload, 2)[0];

        if ($address === '') {
            return [];
        }

        return \explode(',', $address)
            |> ( static fn($recipients) => \array_map(
                callback: static fn(string $recipient): string => \rawurldecode(\trim($recipient)),
                array   : $recipients,
            ) )
            |> ( static fn($recipients) => \array_filter(
                array   : $recipients,
                callback: static fn(string $recipient): bool => $recipient !== '',
            ) );
    }

    private static function parseQueryParam(
        string $mailto,
        string $key,
    ): null|string {
        $parts = \explode('?', $mailto, 2);

        if (! isset($parts[1]) || $parts[1] === '') {
            return null;
        }

        foreach (\explode('&', $parts[1]) as $pair) {
            if ($pair === '') {
                continue;
            }

            $kv = \explode('=', $pair, 2);

            if (\rawurldecode($kv[0]) === $key) {
                return isset($kv[1]) ? \rawurldecode($kv[1]) : '';
            }
        }

        return null;
    }
}

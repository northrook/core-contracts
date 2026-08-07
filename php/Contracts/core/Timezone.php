<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Process timezone wrapper over {@see \DateTimeZone}.
 */
final class Timezone extends \DateTimeZone implements \Stringable
{
    /**
     * Canonical zone id (`Europe/Oslo`, `UTC`, `+02:00`, …).
     *
     * @var non-empty-string
     */
    public string $identifier {
        get => $this->getName()
            ?: throw new RuntimeException(
                message: 'Unable to resolve timezone identifier.',
                context: [__CLASS__ => $this],
            );
    }

    /**
     * Current offset from UTC in **minutes**.
     *
     * For second-resolution at a chosen instant, use {@see getOffset()}.
     */
    public int $offset {
        get => \intdiv($this->getOffset(), 60);
    }

    /**
     * The canonical {@see Timezone::identifier}.
     *
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->identifier;
    }

    /**
     * Offset from UTC in **seconds** at `$datetime` (default: now, UTC clock).
     */
    public function getOffset(
        null|\DateTimeInterface $datetime = null,
    ): int {
        try {
            return parent::getOffset(
                $datetime ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                message : 'Unable to resolve timezone offset.',
                context : [
                    'datetime' => $datetime,
                    'timezone' => $this->getName(),
                ],
                previous: $exception,
            );
        }
    }

    /**
     * Coerce a timezone-like value into a {@see Timezone}.
     *
     * - {@see Timezone} → returned as-is
     * - `int` → minutes from UTC → fixed-offset zone (`+02:00`, …)
     * - {@see \DateTimeInterface} → zone name (or `$default` if none)
     * - {@see \DateTimeZone} → name extracted
     * - string / {@see \Stringable} → trimmed identifier (`Europe/Oslo`, `+02:00`, …)
     * - `null` / empty / missing → `$default`
     *
     * `$default` only fills absence; an invalid identifier still throws.
     *
     * @param null|int|string|\Stringable|\DateTimeZone|\DateTimeInterface  $value
     * @param non-empty-string                                              $default
     *
     * @return \Northrook\Contracts\Timezone
     */
    public static function from(
        null|int|string|\Stringable|\DateTimeZone|\DateTimeInterface $value = null,
        string                                                       $default = 'UTC',
    ): Timezone {
        if ($value instanceof self) {
            return $value;
        }

        $from = $value;

        if (\is_int($value)) {
            $sign    = $value >= 0 ? '+' : '-';
            $abs     = \abs($value);
            $resolve = $sign . \intdiv($abs, 60) . ':' . ( $abs % 60 );
        } else {
            $resolve = $value;
        }

        if ($resolve instanceof \DateTimeInterface) {
            $resolve = $resolve->getTimezone();
        }

        if ($resolve instanceof \DateTimeZone) {
            $resolve = $resolve->getName();
        }

        if ($resolve === false || $resolve === null) {
            $resolve = null;
        } else {
            $resolve = \trim((string) $resolve) ?: null;
        }

        try {
            return new self($resolve ?? $default);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                message : 'Unable to resolve ' . self::class . ' from value.',
                context : [
                    'from'     => $from,
                    'resolved' => $resolve,
                    'default'  => $default,
                ],
                previous: $exception,
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * A Unix timestamp with microseconds.
 */
final readonly class Timestamp implements \Stringable
{
    /**
     * @var float Unix timestamp with microseconds
     */
    public float $number;

    /**
     * @var numeric-string Unix timestamp with microseconds
     */
    public string $string;

    public function __construct(
        null|float $microtime = null,
    ) {
        $microtime ??= \microtime(true);
        $numeric   = \str_replace('.', '', (string) $microtime);

        if (! \ctype_digit($numeric)) {
            throw new \TypeError(
                message: 'Cannot convert ' . \gettype($microtime) . ' to numeric string',
            );
        }

        $this->number = $microtime;
        $this->string = $numeric;
    }

    /**
     * @return string a 12-digit Unix timestamp with microseconds
     */
    public function __toString(): string
    {
        return $this->string;
    }

    public function format(
        string $format = 'Y-m-d\TH:i:sP',
    ): string {
        return \date($format, (int) $this->number);
    }
}

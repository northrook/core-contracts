<?php

declare(strict_types=1);

namespace Northrook\Export;

/**
 * Dump-time token that emits PHP source verbatim.
 *
 * Unlike a plain string, the value is not quoted or escaped by {@see \Northrook\Export}.
 * The dump does not validate the source.
 *
 * @internal
 */
final readonly class RawString
{
    /**
     * @param string $value PHP source to emit.
     */
    private function __construct(
        public string $value,
    ) {}

    /**
     * @param string $value PHP source to emit.
     */
    public static function export(
        string $value,
    ): self {
        return new self($value);
    }
}

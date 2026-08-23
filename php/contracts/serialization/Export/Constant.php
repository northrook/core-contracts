<?php

declare(strict_types=1);

namespace Northrook\Export;

/**
 * Dump-time token for a living name in generated PHP.
 *
 * Leading `\` is stripped, then non-magic names are prefixed with `\`.
 * Names bracketed by `__` (`__CLASS__`, `__DIR__`) are left unprefixed.
 * The dump does not resolve or validate the identifier.
 *
 * @internal
 */
final readonly class Constant
{
    /**
     * @param string $constant PHP source to emit.
     */
    private function __construct(
        public string $constant,
    ) {}

    /**
     * @param string $name Identifier source (`APP_ENV`, `__CLASS__`, `\Foo::BAR`).
     */
    public static function export(
        string $name,
    ): self {
        $name = \ltrim($name, '\\');

        if (\str_starts_with($name, '__') && \str_ends_with($name, '__')) {
            return new self($name);
        }

        return new self('\\' . $name);
    }
}

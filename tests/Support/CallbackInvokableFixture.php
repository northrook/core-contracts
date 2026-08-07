<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class CallbackInvokableFixture
{
    public function __construct(
        private readonly string $prefix = '',
    ) {}

    public function __invoke(
        string ...$parts,
    ): string {
        return $this->prefix . \implode('', $parts);
    }
}

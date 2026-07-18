<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use PHPUnit\Framework\TestCase;

use function Northrook\Contracts\get_hash;

final class GetHashTest extends TestCase
{
    public function testLengthIsSixteen(): void
    {
        self::assertSame(16, \strlen(get_hash()));
    }

    public function testUsesCrockfordBase32(): void
    {
        $hash = get_hash();

        self::assertSame(16, \strspn($hash, \CROCKFORD_BASE32));
        self::assertDoesNotMatchRegularExpression('/[ILOU]/i', $hash);
    }

    public function testSmokeUniqueness(): void
    {
        self::assertNotSame(get_hash(), get_hash());
    }
}

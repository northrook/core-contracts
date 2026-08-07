<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use PHPUnit\Framework\TestCase;

use function Northrook\Contracts\get_hash;

final class GetHashTest extends TestCase
{
    public function testLengthIsSixteen(): void
    {
        self::assertSame(16, strlen(get_hash()));
    }

    public function testUsesCrockfordBase32(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $hash = get_hash();

            self::assertSame(16, strspn($hash, \CROCKFORD_BASE32));
        }
    }

    public function testSmokeUniqueness(): void
    {
        $hashes = [];

        for ($i = 0; $i < 64; $i++) {
            $hashes[] = get_hash();
        }

        self::assertSame($hashes, array_unique($hashes));
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Context\OsFamily;
use PHPUnit\Framework\TestCase;

final class OsFamilyTest extends TestCase
{
    public function testCurrentMatchesPhpOsFamily(): void
    {
        self::assertSame(OsFamily::from(\PHP_OS_FAMILY), OsFamily::current());
    }

    public function testIsMatchesAnyProvidedFamily(): void
    {
        $current = OsFamily::current();

        self::assertTrue($current->is($current));
        self::assertTrue($current->is(OsFamily::Unknown, $current));
        self::assertFalse($current->is());
    }

    public function testWslProbeRequiresLinux(): void
    {
        if (OsFamily::current() !== OsFamily::Linux) {
            self::assertFalse(OsFamily::isWSL());

            return;
        }

        self::assertIsBool(OsFamily::isWSL());
    }
}

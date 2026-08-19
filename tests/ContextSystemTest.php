<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Context;
use Northrook\Context\System;
use Northrook\Singleton;
use PHPUnit\Framework\TestCase;

final class ContextSystemTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetContext();
    }

    protected function tearDown(): void
    {
        $this->resetContext();
    }

    public function testBitnessProbesAreExclusive(): void
    {
        self::assertSame(\PHP_INT_SIZE === 8, System::is(System::x64));
        self::assertSame(\PHP_INT_SIZE === 4, System::is(System::x86));
        self::assertNotSame(System::is(System::x64), System::is(System::x86));
    }

    public function testThreadSafeMatchesBuild(): void
    {
        self::assertSame((bool) \PHP_ZTS, System::is(System::threadSafe));
    }

    public function testDebugBuildMatchesBinary(): void
    {
        self::assertSame((bool) \PHP_DEBUG, System::is(System::debugBuild));
    }

    public function testCliMatchesSapi(): void
    {
        self::assertSame(
            \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg',
            System::is(System::cli),
        );
    }

    public function testTtyRequiresStdout(): void
    {
        $expected = \defined('STDOUT') && \stream_isatty(\STDOUT);

        self::assertSame($expected, System::is(System::tty));
    }

    public function testOpenBasedirMatchesIni(): void
    {
        $expected = (string) \ini_get('open_basedir') !== '';

        self::assertSame($expected, System::is(System::openBasedir));
    }

    public function testContextIsDoesNotRegisterForPlatformProbes(): void
    {
        self::assertFalse(Context::isRegistered());
        self::assertSame(System::is(System::cli), Context::is(System::cli));
        self::assertFalse(Context::isRegistered());
    }

    private function resetContext(): void
    {
        $property = new \ReflectionProperty(Singleton::class, '_instance');
        $property->setValue(null, []);
    }
}

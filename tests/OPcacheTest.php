<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\InvalidArgumentException;
use Northrook\Runtime\OPcache;
use PHPUnit\Framework\TestCase;

final class OPcacheTest extends TestCase
{
    public function testIsLoadedReflectsExtension(): void
    {
        $expected = \extension_loaded('Zend OPcache') || \extension_loaded('opcache');

        self::assertSame($expected, OPcache::isLoaded());
    }

    public function testEnabledImpliesLoaded(): void
    {
        if (OPcache::isEnabled()) {
            self::assertTrue(OPcache::isLoaded());
        }

        self::assertIsBool(OPcache::isEnabled());
        self::assertIsBool(OPcache::isJitEnabled());
    }

    public function testJitEnabledImpliesEnabled(): void
    {
        $jit = OPcache::isJitEnabled();

        self::assertIsBool($jit);

        if ($jit) {
            self::assertTrue(OPcache::isEnabled());
        }
    }

    public function testStatusShapeWhenAvailable(): void
    {
        $status = OPcache::status();

        if ($status === null) {
            self::assertNull($status);

            return;
        }

        self::assertArrayHasKey('opcache_enabled', $status);
        self::assertArrayHasKey('memory_usage', $status);
        self::assertArrayHasKey('opcache_statistics', $status);
        self::assertArrayNotHasKey('scripts', $status);

        $withScripts = OPcache::status(scripts: true);

        if ($withScripts !== null) {
            self::assertArrayHasKey('scripts', $withScripts);
        }
    }

    public function testTelemetryAlwaysReturnsArray(): void
    {
        $telemetry = OPcache::telemetry();
        $status    = OPcache::status();

        self::assertArrayHasKey('available', $telemetry);
        self::assertArrayHasKey('loaded', $telemetry);
        self::assertArrayHasKey('enabled', $telemetry);
        self::assertArrayHasKey('jit_enabled', $telemetry);
        self::assertArrayHasKey('status', $telemetry);

        self::assertSame(OPcache::isLoaded(), $telemetry['loaded']);
        self::assertSame(OPcache::isEnabled(), $telemetry['enabled']);
        self::assertSame(OPcache::isJitEnabled(), $telemetry['jit_enabled']);
        self::assertSame($status !== null, $telemetry['available']);
        self::assertSame($status, $telemetry['status']);

        if ($telemetry['available']) {
            self::assertIsArray($telemetry['status']);
            self::assertArrayHasKey('opcache_enabled', $telemetry['status']);
            self::assertArrayHasKey('memory_usage', $telemetry['status']);
            self::assertArrayHasKey('opcache_statistics', $telemetry['status']);
        } else {
            self::assertNull($telemetry['status']);
        }
    }

    public function testTelemetryStatusNullWhenUnavailable(): void
    {
        if (OPcache::status() !== null) {
            self::markTestSkipped('opcache_get_status available in this SAPI.');
        }

        $telemetry = OPcache::telemetry();

        self::assertFalse($telemetry['available']);
        self::assertNull($telemetry['status']);
        self::assertIsBool($telemetry['loaded']);
        self::assertIsBool($telemetry['enabled']);
        self::assertIsBool($telemetry['jit_enabled']);
    }

    public function testConfigurationWhenLoaded(): void
    {
        $config = OPcache::configuration();

        if (! OPcache::isLoaded() || ! \function_exists('opcache_get_configuration')) {
            self::assertNull($config);

            return;
        }

        if ($config === null) {
            self::markTestSkipped('opcache_get_configuration restricted in this SAPI.');
        }

        self::assertArrayHasKey('directives', $config);
    }

    public function testInvalidateRejectsEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line Testing invalid input.
        OPcache::invalidate('');
    }

    public function testCompileRejectsEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line Testing invalid input.
        OPcache::compile('');
    }

    public function testInvalidateReturnsFalseWhenUnavailable(): void
    {
        if (OPcache::isLoaded() && \function_exists('opcache_invalidate')) {
            self::assertIsBool(OPcache::invalidate(__FILE__));

            return;
        }

        self::assertFalse(OPcache::invalidate(__FILE__));
        self::assertFalse(OPcache::invalidate(__FILE__, force: true));
    }

    public function testCompileReturnsFalseWhenUnavailable(): void
    {
        if (OPcache::isLoaded() && \function_exists('opcache_compile_file')) {
            self::assertIsBool(OPcache::compile(__FILE__));

            return;
        }

        self::assertFalse(OPcache::compile(__FILE__));
    }

    public function testResetReturnsFalseWhenUnavailable(): void
    {
        if (OPcache::isLoaded() && \function_exists('opcache_reset')) {
            self::assertIsBool(OPcache::reset());

            return;
        }

        self::assertFalse(OPcache::reset());
    }

    public function testInvalidateMissingFileDoesNotFatal(): void
    {
        $result = OPcache::invalidate('/tmp/northrook-opcache-missing-' . \uniqid('', true) . '.php');

        self::assertIsBool($result);
    }

    public function testResetDoesNotFatal(): void
    {
        self::assertIsBool(OPcache::reset());
    }
}

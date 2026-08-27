<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Context;
use Northrook\Contracts\Tests\Support\ResetsContext;
use Northrook\Hash;
use Northrook\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\get_temp_path;

final class GetTempPathTest extends TestCase
{
    private const string ROOT = __DIR__ . '/..';

    private const string TEMP_BASENAME = '/^\.temp![0-9A-HJKMNPQRSTVWXYZ]{8}$/';

    private string $unregisteredBase;

    protected function setUp(): void
    {
        ResetsContext::reset();
        $this->unregisteredBase =
            \sys_get_temp_dir()
            . \DIR_SEP
            . Hash::checksum(\dirname(__DIR__) . '/php/functions');
    }

    protected function tearDown(): void
    {
        ResetsContext::reset();
    }

    public function testDefaultPath(): void
    {
        $path = get_temp_path();

        self::assertStringStartsWith($this->unregisteredBase . \DIR_SEP . 'tmp' . \DIR_SEP, $path);
        $this->assertTempHashBasename($path);
    }

    public function testRelativePath(): void
    {
        $path = get_temp_path('cache');

        self::assertStringStartsWith($this->unregisteredBase . \DIR_SEP . 'cache' . \DIR_SEP, $path);
        $this->assertTempHashBasename($path);
    }

    public function testEmptyRelativePathDefaultsToTmp(): void
    {
        $path = get_temp_path('');

        self::assertStringStartsWith($this->unregisteredBase . \DIR_SEP . 'tmp' . \DIR_SEP, $path);
        $this->assertTempHashBasename($path);
    }

    public function testStripsTrailingBang(): void
    {
        $path = get_temp_path('name!!');

        self::assertStringStartsWith($this->unregisteredBase . \DIR_SEP . 'name' . \DIR_SEP, $path);
        self::assertStringNotContainsString('name!!', $path);
        $this->assertTempHashBasename($path);
    }

    public function testNestedRelativePath(): void
    {
        $path = get_temp_path('namespace/cache');

        self::assertStringStartsWith($this->unregisteredBase . \DIR_SEP . 'namespace/cache' . \DIR_SEP, $path);
        $this->assertTempHashBasename($path);
    }

    public function testCollapsesDotAndEmptySegments(): void
    {
        $path = get_temp_path('a/./b//c');

        self::assertStringStartsWith($this->unregisteredBase . \DIR_SEP . 'a/b/c' . \DIR_SEP, $path);
        $this->assertTempHashBasename($path);
    }

    public function testNormalizesBackslashes(): void
    {
        $path = get_temp_path('a\b\c');

        self::assertStringStartsWith($this->unregisteredBase . \DIR_SEP . 'a/b/c' . \DIR_SEP, $path);
        $this->assertTempHashBasename($path);
    }

    public function testSmokeUniqueness(): void
    {
        $paths = [];

        for ($i = 0; $i < 16; $i++) {
            $paths[] = get_temp_path('unique');
        }

        self::assertSame($paths, array_unique($paths));
    }

    public function testUnregisteredUsesSysTempChecksum(): void
    {
        self::assertFalse(Context::isRegistered());

        $path = get_temp_path('download');

        self::assertStringStartsWith($this->unregisteredBase . \DIR_SEP . 'download' . \DIR_SEP, $path);
        $this->assertTempHashBasename($path);
    }

    public function testRegisteredUsesVarTmpSegment(): void
    {
        $context = Context::register(rootDirectory: self::ROOT);
        $prefix  = $context->varDirectory . \DIR_SEP . 'tmp' . \DIR_SEP . 'download' . \DIR_SEP;

        $path = get_temp_path('download');

        self::assertStringStartsWith($prefix, $path);
        $this->assertTempHashBasename($path);
    }

    #[DataProvider('provideTraversalCases')]
    public function testRejectsUpwardTraversal(
        string $relativePath,
    ): void {
        $this->expectException(RuntimeException::class);

        get_temp_path($relativePath);
    }

    public static function provideTraversalCases(): \Generator
    {
        yield 'leading traversal' => ['../evil'];
        yield 'mid-path escape' => ['a/../../b'];
        yield 'trailing parent' => ['a/..'];
    }

    private function assertTempHashBasename(
        string $path,
    ): void {
        self::assertSame(1, \preg_match(self::TEMP_BASENAME, \basename($path)));
    }
}

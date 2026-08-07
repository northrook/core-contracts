<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Singleton;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Northrook\Contracts\get_temp_path;

final class GetTempPathTest extends TestCase
{
    private const string ROOT = __DIR__ . '/..';

    protected function setUp(): void
    {
        $this->resetContracts();
    }

    protected function tearDown(): void
    {
        $this->resetContracts();
    }

    public function testDefaultPath(): void
    {
        $path = get_temp_path();

        self::assertStringStartsWith(\sys_get_temp_dir() . \DIR_SEP . 'tmp!', $path);
        $this->assertBangHashSuffix($path);
    }

    public function testRelativePath(): void
    {
        $path = get_temp_path('cache');

        self::assertStringStartsWith(\sys_get_temp_dir() . \DIR_SEP . 'cache!', $path);
        $this->assertBangHashSuffix($path);
    }

    public function testEmptyRelativePathDefaultsToTmp(): void
    {
        $path = get_temp_path('');

        self::assertStringStartsWith(\sys_get_temp_dir() . \DIR_SEP . 'tmp!', $path);
        $this->assertBangHashSuffix($path);
    }

    public function testStripsTrailingBang(): void
    {
        $path = get_temp_path('name!!');

        self::assertStringStartsWith(\sys_get_temp_dir() . \DIR_SEP . 'name!', $path);
        self::assertStringNotContainsString('name!!', $path);
        $this->assertBangHashSuffix($path);
    }

    public function testNestedRelativePath(): void
    {
        $path = get_temp_path('namespace/cache');

        self::assertStringStartsWith(\sys_get_temp_dir() . \DIR_SEP . 'namespace/cache!', $path);
        $this->assertBangHashSuffix($path);
    }

    public function testCollapsesDotAndEmptySegments(): void
    {
        $path = get_temp_path('a/./b//c');

        self::assertStringStartsWith(\sys_get_temp_dir() . \DIR_SEP . 'a/b/c!', $path);
        $this->assertBangHashSuffix($path);
    }

    public function testNormalizesBackslashes(): void
    {
        $path = get_temp_path('a\b\c');

        self::assertStringStartsWith(\sys_get_temp_dir() . \DIR_SEP . 'a/b/c!', $path);
        $this->assertBangHashSuffix($path);
    }

    public function testSmokeUniqueness(): void
    {
        $paths = [];

        for ($i = 0; $i < 16; $i++) {
            $paths[] = get_temp_path('unique');
        }

        self::assertSame($paths, array_unique($paths));
    }

    public function testUnregisteredUsesSysTemp(): void
    {
        self::assertFalse(Contracts::isRegistered());

        $path = get_temp_path('download');

        self::assertStringStartsWith(\sys_get_temp_dir() . \DIR_SEP . 'download!', $path);
        $this->assertBangHashSuffix($path);
    }

    public function testRegisteredUsesCacheTmpSegment(): void
    {
        $contracts = Contracts::register(rootDirectory: self::ROOT);
        $prefix    = $contracts->cacheDirectory->value . \DIR_SEP . 'tmp' . \DIR_SEP . 'download!';

        $path = get_temp_path('download');

        self::assertStringStartsWith($prefix, $path);
        $this->assertBangHashSuffix($path);
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

    private function assertBangHashSuffix(
        string $path,
    ): void {
        $suffix = (string) strrchr($path, '!');

        self::assertSame(17, strlen($suffix));
        self::assertSame(16, strspn(substr($suffix, 1), \CROCKFORD_BASE32));
    }

    private function resetContracts(): void
    {
        $property = new \ReflectionProperty(Singleton::class, '__instance');
        $property->setValue(null, []);
    }
}

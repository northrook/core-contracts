<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts;
use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\Singleton;
use PHPUnit\Framework\TestCase;

use function Northrook\Contracts\get_checksum;

final class ContractsPathsTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetSingleton(Contracts::class);
        $this->clearRootEnv();
    }

    protected function tearDown(): void
    {
        $this->resetSingleton(Contracts::class);
        $this->clearRootEnv();
    }

    public function testExplicitRootAndCache(): void
    {
        $root  = \realpath(__DIR__ . '/..');
        $cache = \sys_get_temp_dir();

        self::assertNotFalse($root);

        Contracts::register(
            root: $root,
            cache: $cache,
        );

        self::assertSame($root, Contracts::root());
        self::assertSame(\realpath($cache), Contracts::cache());
        self::assertSame(
            \realpath($cache) . \DIR_SEP . 'views',
            Contracts::cache('views'),
        );
    }

    public function testDefaultCacheIsNamespacedSystemTemp(): void
    {
        $root = \realpath(__DIR__ . '/..');
        self::assertNotFalse($root);

        Contracts::register(root: $root);

        self::assertSame(
            \realpath(\sys_get_temp_dir()) . \DIR_SEP . get_checksum($root),
            Contracts::cache(),
        );
    }

    public function testComposerCascadeResolvesThisPackageRoot(): void
    {
        $expected = \realpath(__DIR__ . '/..');
        self::assertNotFalse($expected);

        Contracts::register();

        self::assertSame($expected, Contracts::root());
    }

    public function testEnvOverridesComposer(): void
    {
        $root = \realpath(\sys_get_temp_dir());
        self::assertNotFalse($root);

        \putenv('APPROOT=' . $root);
        $_ENV['APPROOT'] = $root;

        Contracts::register();

        self::assertSame($root, Contracts::root());
    }

    public function testMissingExplicitRootThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('root directory does not exist');

        Contracts::register(root: __DIR__ . '/definitely-missing-' . \uniqid());
    }

    /**
     * @param class-string<Singleton> $class
     */
    private function resetSingleton(
        string $class,
    ): void {
        $property  = new \ReflectionProperty(Singleton::class, '__instance');
        $instances = $property->getValue();
        if (! \is_array($instances)) {
            self::fail('Expected singleton registry array.');
        }
        unset($instances[$class]);
        $property->setValue(null, $instances);
    }

    private function clearRootEnv(): void
    {
        \putenv('APPROOT');
        \putenv('PROJECT_ROOT');
        unset($_ENV['APPROOT'], $_ENV['PROJECT_ROOT'], $_SERVER['APPROOT'], $_SERVER['PROJECT_ROOT']);
    }
}

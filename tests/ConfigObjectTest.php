<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ConfigObject;
use Northrook\Contracts\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;

final class ConfigObjectTest extends TestCase
{
    public function testFromSpreadsOptionsIntoConstructor(): void
    {
        $config = TestConfigObject::from([
            'name'  => 'example',
            'count' => 5,
        ]);

        self::assertInstanceOf(TestConfigObject::class, $config);
        self::assertSame('example', $config->name);
        self::assertSame(5, $config->count);
    }

    public function testFromAppliesDefaults(): void
    {
        $config = TestConfigObject::from([]);

        self::assertSame(__NAMESPACE__, $config->name);
        self::assertSame(0, $config->count);
    }

    public function testFromOverridesDefaults(): void
    {
        $config = TestConfigObject::from([
            'name' => 'example',
        ]);

        self::assertSame('example', $config->name);
        self::assertSame(0, $config->count);
    }

    public function testFromResolvesCallableStringDefaults(): void
    {
        $config = ComputedConfigObject::from([
            'prefix' => 'app',
        ]);

        self::assertSame('app-computed', $config->name);
        self::assertSame(0, $config->count);
    }

    public function testFromDoesNotInvokeCallableDefaultWhenKeyProvided(): void
    {
        $config = ComputedConfigObject::from([
            'prefix' => 'app',
            'name'   => 'explicit',
        ]);

        self::assertSame('explicit', $config->name);
    }

    public function testFromThrowsWhenCallableDefaultFails(): void
    {
        try {
            FailingComputedConfigObject::from([]);
            self::fail('Expected RuntimeException.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Failed to create ' . FailingComputedConfigObject::class . ' from config array.',
                $exception->getMessage(),
            );
            $previous = $exception->getPrevious();
            self::assertInstanceOf(RuntimeException::class, $previous);
            self::assertSame('Failed to resolve config `name`', $previous->getMessage());
        }
    }

    public function testFromThrowsOnMissingRequiredParameters(): void
    {
        try {
            RequiredConfigObject::from([
                // Missing required `name` (DEFAULTS null sentinel).
                'count' => 1,
            ]);
            self::fail('Expected RuntimeException.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Failed to create ' . RequiredConfigObject::class . ' from config array.',
                $exception->getMessage(),
            );
            $previous = $exception->getPrevious();
            self::assertInstanceOf(RuntimeException::class, $previous);
            self::assertSame('Missing required config `name`', $previous->getMessage());
        }
    }

    public function testFromThrowsOnIncompatibleTypes(): void
    {
        try {
            TestConfigObject::from([
                'name'  => 'example',
                'count' => '1', // `int` expected.
            ]);
            self::fail('Expected RuntimeException.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Failed to create ' . TestConfigObject::class . ' from config array.',
                $exception->getMessage(),
            );
            self::assertInstanceOf(\TypeError::class, $exception->getPrevious());
        }
    }

    public function testFromThrowsOnUnknownKeys(): void
    {
        try {
            TestConfigObject::from([
                'name'    => 'example',
                'count'   => 1,
                'unknown' => true,
            ]);
            self::fail('Expected RuntimeException.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Failed to create ' . TestConfigObject::class . ' from config array.',
                $exception->getMessage(),
            );
            $previous = $exception->getPrevious();
            self::assertInstanceOf(RuntimeException::class, $previous);
            self::assertSame('Unknown config keys: unknown', $previous->getMessage());
        }
    }
}

final readonly class TestConfigObject extends ConfigObject
{
    const array DEFAULTS = [
        'name'  => __NAMESPACE__,
        'count' => 0,
    ];

    public function __construct(
        public string $name,
        public int $count = 0,
    ) {
        parent::__construct();
    }
}

final readonly class RequiredConfigObject extends ConfigObject
{
    const array DEFAULTS = [
        'name'  => null,
        'count' => 0,
    ];

    public function __construct(
        public string $name,
        public int $count = 0,
    ) {
        parent::__construct();
    }
}

final readonly class ComputedConfigObject extends ConfigObject
{
    const array DEFAULTS = [
        'prefix' => null,
        'name'   => self::class . '::computeName',
        'count'  => 0,
    ];

    public function __construct(
        public string $prefix,
        public string $name,
        public int $count = 0,
    ) {
        parent::__construct();
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function computeName(
        array $config,
    ): string {
        $prefix = $config['prefix'] ?? 'missing';

        return ( \is_string($prefix) ? $prefix : 'missing' ) . '-computed';
    }
}

final readonly class FailingComputedConfigObject extends ConfigObject
{
    const array DEFAULTS = [
        'name'  => self::class . '::fail',
        'count' => 0,
    ];

    public function __construct(
        public string $name,
        public int $count = 0,
    ) {
        parent::__construct();
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fail(
        array $config,
    ): string {
        throw new \RuntimeException('boom');
    }
}

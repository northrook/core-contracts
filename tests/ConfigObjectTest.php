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

    public function testFromThrowsOnMissingRequiredParameters(): void
    {
        try {
            RequiredConfigObject::from([
                // Missing required `name`; no DEFAULTS cover it.
                'count' => 1,
            ]);
            self::fail('Expected RuntimeException.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Failed to create ' . RequiredConfigObject::class . ' from config array.',
                $exception->getMessage(),
            );
            self::assertInstanceOf(\ArgumentCountError::class, $exception->getPrevious());
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
            self::assertInstanceOf(\Error::class, $exception->getPrevious());
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
    const array DEFAULTS = [];

    public function __construct(
        public string $name,
        public int $count = 0,
    ) {
        parent::__construct();
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ConfigObject;
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

    public function testFromAppliesConstructorDefaults(): void
    {
        $config = TestConfigObject::from([
            'name' => 'example',
        ]);

        self::assertSame('example', $config->name);
        self::assertSame(0, $config->count);
    }

    public function testFromThrowsOnMissingRequiredParameters(): void
    {
        $this->expectException(\ArgumentCountError::class);

        TestConfigObject::from([
            // Missing required `name`.
            'count' => 1,
        ]);
    }

    public function testFromThrowsOnIncompatibleTypes(): void
    {
        $this->expectException(\TypeError::class);

        TestConfigObject::from([
            'name'  => 'example',
            'count' => '1', // `int` expected.
        ]);
    }

    public function testFromThrowsOnUnknownKeys(): void
    {
        $this->expectException(\Error::class);

        TestConfigObject::from([
            'name'   => 'example',
            'count'  => 1,
            'unknown' => true,
        ]);
    }
}

final readonly class TestConfigObject extends ConfigObject
{
    public function __construct(
        public string $name,
        public int $count = 0,
    ) {
        parent::__construct();
    }
}


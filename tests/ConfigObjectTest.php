<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\ConfigObject;
use Northrook\RuntimeException;
use PHPUnit\Framework\TestCase;

/**
 * @param array<string, mixed> $config
 */
function configObjectTestComputedLabel(
    array $config,
): string {
    $name = $config['name'] ?? 'missing';

    return 'computed:' . ( \is_string($name) ? $name : 'missing' );
}

/**
 * @param array<string, mixed> $config
 */
function configObjectTestFailingDefault(
    array $config,
): string {
    throw new \LogicException('Default exploded.');
}

final class ConfigObjectTest extends TestCase
{
    public function testFromSpreadsOptionsIntoConstructor(): void
    {
        $config = ConfigObjectTestBasic::from([
            'name'  => 'app',
            'count' => 3,
            'label' => 'custom',
        ]);

        self::assertSame('app', $config->name);
        self::assertSame(3, $config->count);
        self::assertSame('custom', $config->label);
    }

    public function testFromAppliesDefaultsForAbsentKeys(): void
    {
        $config = ConfigObjectTestBasic::from(['name' => 'app']);

        self::assertSame('app', $config->name);
        self::assertSame(7, $config->count);
        self::assertSame('fallback', $config->label);
    }

    public function testFromResolvesCallableStringDefaults(): void
    {
        $config = ConfigObjectTestComputed::from(['name' => 'app']);

        self::assertSame('app', $config->name);
        self::assertSame('computed:app', $config->label);
    }

    public function testFromDoesNotInvokeCallableDefaultWhenKeyProvided(): void
    {
        $config = ConfigObjectTestComputed::from([
            'name'  => 'app',
            'label' => 'explicit',
        ]);

        self::assertSame('explicit', $config->label);
    }

    public function testFromThrowsWhenCallableDefaultFails(): void
    {
        try {
            ConfigObjectTestFailingComputed::from([]);
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Failed to create', $exception->getMessage());

            $previous = $exception->getPrevious();

            self::assertInstanceOf(RuntimeException::class, $previous);
            self::assertStringContainsString('Failed to resolve config `label`', $previous->getMessage());
            self::assertInstanceOf(\LogicException::class, $previous->getPrevious());
        }
    }

    public function testFromThrowsOnMissingRequiredParameters(): void
    {
        try {
            ConfigObjectTestBasic::from([]);
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Failed to create', $exception->getMessage());

            $previous = $exception->getPrevious();

            self::assertInstanceOf(RuntimeException::class, $previous);
            self::assertStringContainsString('Missing required config `name`', $previous->getMessage());
        }
    }

    public function testFromThrowsOnIncompatibleTypes(): void
    {
        try {
            ConfigObjectTestBasic::from(['name' => 'app', 'count' => 'lots']);
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Failed to create', $exception->getMessage());
            self::assertInstanceOf(\TypeError::class, $exception->getPrevious());
        }
    }

    public function testFromThrowsOnUnknownKeys(): void
    {
        try {
            ConfigObjectTestBasic::from(['name' => 'app', 'bogus' => 1, 'extra' => 2]);
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Failed to create', $exception->getMessage());

            $previous = $exception->getPrevious();

            self::assertInstanceOf(RuntimeException::class, $previous);
            self::assertStringContainsString('Unknown config keys: bogus, extra', $previous->getMessage());
        }
    }

    public function testFromKeepsExplicitNullForRequiredKey(): void
    {
        $config = ConfigObjectTestNullable::from(['note' => null]);

        self::assertNull($config->note);
    }
}

final readonly class ConfigObjectTestBasic extends ConfigObject
{
    public const array DEFAULTS = [
        'name'  => null,
        'count' => 7,
        'label' => 'fallback',
    ];

    public function __construct(
        public string $name,
        public int    $count,
        public string $label,
    ) {
        parent::__construct();
    }
}

final readonly class ConfigObjectTestComputed extends ConfigObject
{
    public const array DEFAULTS = [
        'name'  => null,
        'label' => 'Northrook\Contracts\Tests\configObjectTestComputedLabel',
    ];

    public function __construct(
        public string $name,
        public string $label,
    ) {
        parent::__construct();
    }
}

final readonly class ConfigObjectTestFailingComputed extends ConfigObject
{
    public const array DEFAULTS = [
        'label' => 'Northrook\Contracts\Tests\configObjectTestFailingDefault',
    ];

    public function __construct(
        public string $label,
    ) {
        parent::__construct();
    }
}

final readonly class ConfigObjectTestNullable extends ConfigObject
{
    public const array DEFAULTS = [
        'note' => null,
    ];

    public function __construct(
        public null|string $note,
    ) {
        parent::__construct();
    }
}

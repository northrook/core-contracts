<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts;
use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\Singleton;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SingletonTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetSingleton(Contracts::class);
        $this->resetSingleton(SingletonTestClock::class);
    }

    protected function tearDown(): void
    {
        $this->resetSingleton(Contracts::class);
        $this->resetSingleton(SingletonTestClock::class);
    }

    public function testZeroArgSubclassLazyGetsOnce(): void
    {
        self::assertFalse(SingletonTestClock::isRegistered());

        $first  = SingletonTestClock::get();
        $second = SingletonTestClock::get();

        self::assertTrue(SingletonTestClock::isRegistered());
        self::assertSame($first, $second);
        self::assertTrue(
            new \ReflectionProperty(Singleton::class, '__selfInstantiated')->getValue($first),
        );
    }

    public function testContractsRegisterThenGet(): void
    {
        $logger     = new NullLogger();
        $registered = Contracts::register($logger);

        self::assertTrue(Contracts::isRegistered());
        self::assertSame($registered, Contracts::get());
        self::assertSame($logger, Contracts::log());
        self::assertSame('UTC', Contracts::timezone()->getName());
    }

    public function testContractsGetAutoRegisters(): void
    {
        self::assertFalse(Contracts::isRegistered());

        $logger = Contracts::log();

        self::assertTrue(Contracts::isRegistered());
        self::assertInstanceOf(NullLogger::class, $logger);
    }

    public function testSecondConstructThrows(): void
    {
        Contracts::register();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already registered');

        Contracts::register();
    }

    public function testCloneThrows(): void
    {
        $clock = SingletonTestClock::get();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be cloned');

        $unused = clone $clock;
        unset($unused);
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
}

/**
 * @internal
 */
final class SingletonTestClock extends Singleton {}

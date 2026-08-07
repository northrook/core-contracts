<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\LogicException;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Singleton;
use Northrook\Contracts\Timestamp;
use PHPUnit\Framework\TestCase;

final class SingletonTest extends TestCase
{
    protected function setUp(): void
    {
        self::resetRegistry();
    }

    protected function tearDown(): void
    {
        self::resetRegistry();
    }

    public function testGetLazilyCreatesAndMemoizesInstance(): void
    {
        self::assertFalse(SingletonTestClock::isRegistered());

        $first = SingletonTestClock::get();

        self::assertTrue(SingletonTestClock::isRegistered());
        self::assertTrue($first->selfInstantiated());
        self::assertInstanceOf(Timestamp::class, $first->timestamp());
        self::assertSame($first, SingletonTestClock::get());
    }

    public function testRegisterThenGetReturnsRegisteredInstance(): void
    {
        $registered = SingletonTestGreeting::register('hello');

        self::assertFalse($registered->selfInstantiated());
        self::assertSame('hello', $registered->greeting);
        self::assertSame($registered, SingletonTestGreeting::get());
    }

    public function testSecondConstructThrows(): void
    {
        SingletonTestGreeting::register('first');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is already registered and cannot be instantiated twice.');

        SingletonTestGreeting::register('second');
    }

    public function testCloneThrows(): void
    {
        $instance = SingletonTestClock::get();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is a singleton and cannot be cloned.');

        // @phpstan-ignore-next-line Testing clone rejection.
        clone $instance;
    }

    public function testGetWrapsCreateFailure(): void
    {
        try {
            SingletonTestGreeting::get();
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('failed to initialize via get().', $exception->getMessage());
            self::assertInstanceOf(\LogicException::class, $exception->getPrevious());
        }
    }

    public function testResettableUnregisterVacatesSlot(): void
    {
        $first = SingletonTestClock::get();
        $first->release(resettable: true);

        self::assertFalse(SingletonTestClock::isRegistered());

        $second = SingletonTestClock::get();

        self::assertNotSame($first, $second);
    }

    public function testPermanentUnregisterBurnsSlot(): void
    {
        $instance = SingletonTestClock::get();
        $instance->release(resettable: false);

        self::assertFalse(SingletonTestClock::isRegistered());

        try {
            SingletonTestClock::get();
            self::fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString(
                'was permanently unregistered and cannot be retrieved.',
                $exception->getMessage(),
            );
        }
    }

    public function testPermanentUnregisterBlocksNewConstruction(): void
    {
        SingletonTestGreeting::register('first')->release(resettable: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('was permanently unregistered and cannot be instantiated again.');

        SingletonTestGreeting::register('second');
    }

    public function testSubclassesHaveIndependentSlots(): void
    {
        $clock    = SingletonTestClock::get();
        $greeting = SingletonTestGreeting::register('hi');

        self::assertTrue(SingletonTestClock::isRegistered());
        self::assertTrue(SingletonTestGreeting::isRegistered());
        self::assertNotSame($clock, $greeting);
    }

    private static function resetRegistry(): void
    {
        $registry = new \ReflectionProperty(Singleton::class, '__instance');
        $registry->setValue(null, []);
    }
}

final class SingletonTestClock extends Singleton
{
    public function release(
        bool $resettable,
    ): void {
        $this->unregisterSingletonInstance($resettable);
    }

    public function selfInstantiated(): bool
    {
        return $this->__selfInstantiated;
    }

    public function timestamp(): Timestamp
    {
        return $this->__timestamp;
    }
}

final class SingletonTestGreeting extends Singleton
{
    private function __construct(
        public readonly string $greeting,
        bool                   $__selfInstantiated = false,
    ) {
        parent::__construct($__selfInstantiated);
    }

    public static function register(
        string $greeting,
    ): static {
        return new self($greeting);
    }

    public function release(
        bool $resettable,
    ): void {
        $this->unregisterSingletonInstance($resettable);
    }

    public function selfInstantiated(): bool
    {
        return $this->__selfInstantiated;
    }

    protected static function create(): static
    {
        throw new \LogicException(self::class . ' must be register()ed before get()');
    }
}

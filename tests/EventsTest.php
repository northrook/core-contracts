<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Container\CompilerInterface;
use Northrook\Contracts\Tests\Support\OnEventSampleEvent;
use Northrook\Contracts\Tests\Support\OnEventStandaloneEvent;
use Northrook\Event;
use Northrook\EventDispatcherInterface;
use Northrook\EventInterface;
use Northrook\Events\ListenerMapInterface;
use Northrook\ListenerProviderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

final class EventsTest extends TestCase
{
    // ── Contracts ────────────────────────────────────────────────

    /**
     * @param class-string $contract
     */
    #[DataProvider('provideInterfaces')]
    public function testContractIsInterface(
        string $contract,
    ): void {
        self::assertTrue(new \ReflectionClass($contract)->isInterface());
    }

    /**
     * @return \Generator<string, array{class-string}>
     */
    public static function provideInterfaces(): \Generator
    {
        yield 'EventInterface' => [EventInterface::class];
        yield 'EventDispatcherInterface' => [EventDispatcherInterface::class];
        yield 'ListenerProviderInterface' => [ListenerProviderInterface::class];
        yield 'ListenerMapInterface' => [ListenerMapInterface::class];
    }

    public function testEventInterfaceExtendsPsrStoppable(): void
    {
        self::assertContains(
            StoppableEventInterface::class,
            new \ReflectionClass(EventInterface::class)->getInterfaceNames(),
        );
    }

    public function testEventDispatcherInterfaceExtendsPsrDispatcher(): void
    {
        self::assertContains(
            PsrEventDispatcherInterface::class,
            new \ReflectionClass(EventDispatcherInterface::class)->getInterfaceNames(),
        );
    }

    public function testListenerProviderInterfaceExtendsPsrProvider(): void
    {
        self::assertContains(
            PsrListenerProviderInterface::class,
            new \ReflectionClass(ListenerProviderInterface::class)->getInterfaceNames(),
        );
    }

    public function testCompilerInterfaceExposesListenerMap(): void
    {
        $property = new \ReflectionProperty(CompilerInterface::class, 'listeners');

        self::assertSame(ListenerMapInterface::class, (string) $property->getType());
    }

    public function testListenerMapInterfaceDeclaresAddForHas(): void
    {
        $reflection = new \ReflectionClass(ListenerMapInterface::class);

        self::assertTrue($reflection->hasMethod('add'));
        self::assertTrue($reflection->hasMethod('for'));
        self::assertTrue($reflection->hasMethod('has'));
        self::assertFalse($reflection->hasMethod('sorted'));
        self::assertFalse($reflection->hasMethod('all'));
    }

    public function testEventIsAbstract(): void
    {
        self::assertTrue(new \ReflectionClass(Event::class)->isAbstract());
        self::assertTrue(new \ReflectionClass(OnEventSampleEvent::class)->isSubclassOf(Event::class));
    }

    // ── Propagation ──────────────────────────────────────────────

    public function testEventPropagationDefaultsToRunning(): void
    {
        $event = new OnEventSampleEvent;

        self::assertFalse($event->isPropagationStopped());
    }

    public function testEventStopPropagation(): void
    {
        $event = new OnEventSampleEvent;
        $event->stopPropagation();

        self::assertTrue($event->isPropagationStopped());
    }

    public function testStandaloneEventStopPropagation(): void
    {
        $event = new OnEventStandaloneEvent;

        self::assertFalse($event->isPropagationStopped());

        $event->stopPropagation();

        self::assertTrue($event->isPropagationStopped());
    }
}

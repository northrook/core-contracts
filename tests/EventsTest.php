<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Container\Compiler\ListenerRegistryInterface;
use Northrook\Contracts\Tests\Support\OnEventSampleEvent;
use Northrook\Contracts\Tests\Support\OnEventStandaloneEvent;
use Northrook\Event;
use Northrook\EventDispatcherInterface;
use Northrook\EventInterface;
use Northrook\Events\ListenerMap;
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
        yield 'ListenerRegistryInterface' => [ListenerRegistryInterface::class];
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

    public function testListenerRegistryInterfaceDeclaresRegisterForHasToListenerMap(): void
    {
        $reflection = new \ReflectionClass(ListenerRegistryInterface::class);

        self::assertTrue($reflection->hasMethod('register'));
        self::assertTrue($reflection->hasMethod('for'));
        self::assertTrue($reflection->hasMethod('has'));
        self::assertTrue($reflection->hasMethod('toListenerMap'));
        self::assertFalse($reflection->hasMethod('add'));
        self::assertFalse($reflection->hasMethod('sorted'));
        self::assertFalse($reflection->hasMethod('all'));

        $return = (string) $reflection->getMethod('toListenerMap')->getReturnType();
        self::assertSame(ListenerMap::class, $return);
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

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Tests\Support\OnEventExactListener;
use Northrook\Contracts\Tests\Support\OnEventInterfaceListener;
use Northrook\Contracts\Tests\Support\OnEventOtherEvent;
use Northrook\Contracts\Tests\Support\OnEventSampleEvent;
use Northrook\Contracts\Tests\Support\OnEventStandaloneEvent;
use Northrook\Event;
use Northrook\EventInterface;
use Northrook\Events\EventListeners;
use Northrook\Events\ListenerDescriptor;
use PHPUnit\Framework\TestCase;

final class EventListenersTest extends TestCase
{
    // ── ListenerDescriptor ───────────────────────────────────────

    public function testDescriptorStoresBindingsAndDefaultPriority(): void
    {
        $descriptor = new ListenerDescriptor(
            OnEventSampleEvent::class,
            OnEventExactListener::class,
            'onExact',
        );

        self::assertSame(OnEventSampleEvent::class, $descriptor->event);
        self::assertSame(OnEventExactListener::class, $descriptor->class);
        self::assertSame('onExact', $descriptor->method);
        self::assertSame(0, $descriptor->priority);
    }

    public function testDescriptorStoresCustomPriority(): void
    {
        $descriptor = new ListenerDescriptor(
            OnEventSampleEvent::class,
            OnEventExactListener::class,
            'onExact',
            10,
        );

        self::assertSame(10, $descriptor->priority);
    }

    // ── EventListeners ───────────────────────────────────────────

    public function testEmptyMapHasNoListeners(): void
    {
        $listeners = new EventListeners;

        self::assertSame([], $listeners->listeners);
        self::assertFalse($listeners->has(OnEventSampleEvent::class));
        self::assertSame([], $listeners->for(OnEventSampleEvent::class));
        self::assertSame([], $listeners->sorted(OnEventSampleEvent::class));
    }

    public function testForMatchesExactEvent(): void
    {
        $exact = new ListenerDescriptor(
            OnEventSampleEvent::class,
            OnEventExactListener::class,
            'onExact',
        );
        $other = new ListenerDescriptor(
            OnEventOtherEvent::class,
            OnEventExactListener::class,
            'onExact',
        );

        $listeners = new EventListeners([$exact, $other]);

        self::assertSame([$exact], $listeners->for(OnEventSampleEvent::class));
        self::assertSame([$other], $listeners->for(OnEventOtherEvent::class));
    }

    public function testForMatchesParentAndInterfaceRegistrations(): void
    {
        $onBase = new ListenerDescriptor(
            Event::class,
            OnEventInterfaceListener::class,
            'onInterface',
        );
        $onContract = new ListenerDescriptor(
            EventInterface::class,
            OnEventInterfaceListener::class,
            'onInterface',
            5,
        );

        $listeners = new EventListeners([$onBase, $onContract]);

        self::assertSame(
            [$onBase, $onContract],
            $listeners->for(OnEventSampleEvent::class),
        );
        self::assertSame(
            [$onContract],
            $listeners->for(OnEventStandaloneEvent::class),
        );
    }

    public function testForDoesNotMatchSiblingOrParentQuery(): void
    {
        $exact = new ListenerDescriptor(
            OnEventSampleEvent::class,
            OnEventExactListener::class,
            'onExact',
        );

        $listeners = new EventListeners([$exact]);

        self::assertSame([], $listeners->for(OnEventOtherEvent::class));
        self::assertSame([], $listeners->for(Event::class));
        self::assertSame([], $listeners->for(EventInterface::class));
    }

    public function testForAcceptsEventInstance(): void
    {
        $exact = new ListenerDescriptor(
            OnEventSampleEvent::class,
            OnEventExactListener::class,
            'onExact',
        );

        $listeners = new EventListeners([$exact]);

        self::assertSame([$exact], $listeners->for(new OnEventSampleEvent));
        self::assertSame([], $listeners->for(new OnEventOtherEvent));
    }

    public function testForPreservesRegistrationOrder(): void
    {
        $first = new ListenerDescriptor(
            OnEventSampleEvent::class,
            OnEventExactListener::class,
            'onExact',
            1,
        );
        $second = new ListenerDescriptor(
            EventInterface::class,
            OnEventInterfaceListener::class,
            'onInterface',
            99,
        );

        $listeners = new EventListeners([$first, $second]);

        self::assertSame([$first, $second], $listeners->for(OnEventSampleEvent::class));
    }

    public function testSortedOrdersByPriorityDescending(): void
    {
        $low = new ListenerDescriptor(
            OnEventSampleEvent::class,
            OnEventExactListener::class,
            'onExact',
            1,
        );
        $high = new ListenerDescriptor(
            EventInterface::class,
            OnEventInterfaceListener::class,
            'onInterface',
            10,
        );
        $mid = new ListenerDescriptor(
            Event::class,
            OnEventInterfaceListener::class,
            'onInterface',
            5,
        );

        $listeners = new EventListeners([$low, $high, $mid]);

        self::assertSame([$high, $mid, $low], $listeners->sorted(OnEventSampleEvent::class));
        self::assertSame([$low, $high, $mid], $listeners->for(OnEventSampleEvent::class));
    }

    public function testHasIsTrueWhenForIsNonEmpty(): void
    {
        $listeners = new EventListeners([
            new ListenerDescriptor(
                EventInterface::class,
                OnEventInterfaceListener::class,
                'onInterface',
            ),
        ]);

        self::assertTrue($listeners->has(OnEventSampleEvent::class));
        self::assertTrue($listeners->has(new OnEventStandaloneEvent));
        self::assertFalse($listeners->has(\stdClass::class));
    }
}

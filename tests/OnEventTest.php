<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Tests\Support\OnEventAbstractListener;
use Northrook\Contracts\Tests\Support\OnEventBadListeners;
use Northrook\Contracts\Tests\Support\OnEventExactListener;
use Northrook\Contracts\Tests\Support\OnEventInterfaceListener;
use Northrook\Contracts\Tests\Support\OnEventIntersectionListener;
use Northrook\Contracts\Tests\Support\OnEventIntersectionRejectListener;
use Northrook\Contracts\Tests\Support\OnEventMarkedEvent;
use Northrook\Contracts\Tests\Support\OnEventMixedUnionListener;
use Northrook\Contracts\Tests\Support\OnEventNullableListener;
use Northrook\Contracts\Tests\Support\OnEventOtherEvent;
use Northrook\Contracts\Tests\Support\OnEventRepeatableListener;
use Northrook\Contracts\Tests\Support\OnEventSampleEvent;
use Northrook\Contracts\Tests\Support\OnEventSecondBindingListener;
use Northrook\Contracts\Tests\Support\OnEventStandaloneEvent;
use Northrook\Contracts\Tests\Support\OnEventUnionListener;
use Northrook\Contracts\Tests\Support\OnEventUnionRejectListener;
use Northrook\Event;
use Northrook\EventInterface;
use Northrook\InvalidArgumentException;
use Northrook\OnEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OnEventTest extends TestCase
{
    // ── Attribute shape ──────────────────────────────────────────

    public function testAttributeTargetsMethodAndIsRepeatable(): void
    {
        $attributes = new \ReflectionClass(OnEvent::class)->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        self::assertSame(
            \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            $attributes[0]->newInstance()->flags,
        );
    }

    // ── Constructor ──────────────────────────────────────────────

    public function testConstructorAcceptsConcreteEventClass(): void
    {
        $attribute = new OnEvent(OnEventSampleEvent::class);

        self::assertSame(OnEventSampleEvent::class, $attribute->event);
        self::assertSame(0, $attribute->priority);
    }

    public function testConstructorAcceptsEventInterface(): void
    {
        $attribute = new OnEvent(EventInterface::class);

        self::assertSame(EventInterface::class, $attribute->event);
    }

    public function testConstructorAcceptsAbstractEventBase(): void
    {
        $attribute = new OnEvent(Event::class);

        self::assertSame(Event::class, $attribute->event);
    }

    public function testConstructorAcceptsStandaloneEventInterfaceImplementation(): void
    {
        $attribute = new OnEvent(OnEventStandaloneEvent::class);

        self::assertSame(OnEventStandaloneEvent::class, $attribute->event);
    }

    public function testConstructorStoresCustomPriority(): void
    {
        $attribute = new OnEvent(OnEventSampleEvent::class, priority: 42);

        self::assertSame(42, $attribute->priority);
    }

    #[DataProvider('provideInvalidEventTypes')]
    public function testConstructorRejectsNonEventTypes(
        string $eventClass,
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement ' . EventInterface::class);

        new OnEvent($eventClass);
    }

    public function testConstructorRejectionCarriesContext(): void
    {
        try {
            new OnEvent(\stdClass::class);
            self::fail('Expected InvalidArgumentException.');
        }
        catch (InvalidArgumentException $exception) {
            self::assertSame(['event' => \stdClass::class, 'errors' => []], $exception->getContext());
        }
    }

    /**
     * @return \Generator<string, array{0: class-string}>
     */
    public static function provideInvalidEventTypes(): \Generator
    {
        yield 'stdClass' => [\stdClass::class];
        yield 'stringable interface' => [\Stringable::class];
    }

    // ── register() success ───────────────────────────────────────

    #[DataProvider('provideValidListenerBindings')]
    public function testRegisterAcceptsValidListenerSignatures(
        string $listenerClass,
        string $method,
        string $eventClass,
    ): void {
        $attribute = new OnEvent($eventClass);

        $returned = $attribute->register($listenerClass, $method);

        self::assertSame($attribute, $returned);
        self::assertSame($listenerClass, $attribute->class);
        self::assertSame($method, $attribute->method);
    }

    /**
     * @return \Generator<string, array{0: class-string, 1: non-empty-string, 2: class-string<EventInterface>}>
     */
    public static function provideValidListenerBindings(): \Generator
    {
        yield 'exact event type' => [
            OnEventExactListener::class,
            'onExact',
            OnEventSampleEvent::class,
        ];
        yield 'event interface' => [
            OnEventInterfaceListener::class,
            'onInterface',
            OnEventSampleEvent::class,
        ];
        yield 'abstract event base' => [
            OnEventAbstractListener::class,
            'onAbstract',
            OnEventSampleEvent::class,
        ];
        yield 'union including event' => [
            OnEventUnionListener::class,
            'onUnion',
            OnEventSampleEvent::class,
        ];
        yield 'union sibling event' => [
            OnEventUnionListener::class,
            'onUnion',
            OnEventOtherEvent::class,
        ];
        yield 'intersection accepting event' => [
            OnEventIntersectionListener::class,
            'onIntersection',
            OnEventMarkedEvent::class,
        ];
        yield 'nullable union' => [
            OnEventNullableListener::class,
            'onNullable',
            OnEventSampleEvent::class,
        ];
        yield 'mixed union with class branch' => [
            OnEventMixedUnionListener::class,
            'onMixed',
            OnEventSampleEvent::class,
        ];
        yield 'standalone event interface impl' => [
            OnEventInterfaceListener::class,
            'onInterface',
            OnEventStandaloneEvent::class,
        ];
    }

    public function testRegisterIsIdempotentForSameBinding(): void
    {
        $attribute = new OnEvent(OnEventSampleEvent::class);
        $attribute->register(OnEventExactListener::class, 'onExact');

        $returned = $attribute->register(OnEventExactListener::class, 'onExact');

        self::assertSame($attribute, $returned);
        self::assertSame(OnEventExactListener::class, $attribute->class);
        self::assertSame('onExact', $attribute->method);
    }

    // ── register() failures ──────────────────────────────────────

    public function testRegisterRejectsConflictingRebind(): void
    {
        $attribute = new OnEvent(OnEventSampleEvent::class);
        $attribute->register(OnEventSecondBindingListener::class, 'first');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("already registered on '" . OnEventSecondBindingListener::class . "::first'");

        $attribute->register(OnEventSecondBindingListener::class, 'second');
    }

    public function testRegisterConflictingRebindCarriesContext(): void
    {
        $attribute = new OnEvent(OnEventSampleEvent::class);
        $attribute->register(OnEventSecondBindingListener::class, 'first');

        try {
            $attribute->register(OnEventSecondBindingListener::class, 'second');
            self::fail('Expected InvalidArgumentException.');
        }
        catch (InvalidArgumentException $exception) {
            self::assertSame(
                [
                    'event'  => OnEventSampleEvent::class,
                    'class'  => OnEventSecondBindingListener::class,
                    'method' => 'second',
                    'bound'  => OnEventSecondBindingListener::class . '::first',
                    'errors' => [],
                ],
                $exception->getContext(),
            );
        }
    }

    public function testRegisterRejectsEventWiderThanListenerParameter(): void
    {
        $attribute = new OnEvent(EventInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("must accept '" . EventInterface::class . "'");

        $attribute->register(OnEventExactListener::class, 'onExact');
    }

    public function testRegisterRejectsMissingClass(): void
    {
        $attribute = new OnEvent(OnEventSampleEvent::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $attribute->register('Northrook\\ThisListenerClassDoesNotExist', 'onEvent');
    }

    public function testRegisterRejectsMissingMethod(): void
    {
        $attribute = new OnEvent(OnEventSampleEvent::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('method does not exist');

        $attribute->register(OnEventExactListener::class, 'missingMethod');
    }

    #[DataProvider('provideInvalidListenerMethods')]
    public function testRegisterRejectsInvalidListenerMethods(
        string $method,
        string $messageFragment,
    ): void {
        $attribute = new OnEvent(OnEventSampleEvent::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($messageFragment);

        $attribute->register(OnEventBadListeners::class, $method);
    }

    /**
     * @return \Generator<string, array{0: non-empty-string, 1: non-empty-string}>
     */
    public static function provideInvalidListenerMethods(): \Generator
    {
        yield 'protected' => ['protectedListener', 'must be a public instance method'];
        yield 'private' => ['privateListener', 'must be a public instance method'];
        yield 'static' => ['staticListener', 'must be a public instance method'];
        yield 'no parameters' => ['noParams', 'must declare the event as parameter 0'];
        yield 'untyped parameter' => ['untyped', "must accept '" . OnEventSampleEvent::class . "'"];
        yield 'builtin parameter' => ['stringParam', "must accept '" . OnEventSampleEvent::class . "'"];
        yield 'unrelated class parameter' => ['wrongClass', "must accept '" . OnEventSampleEvent::class . "'"];
    }

    public function testRegisterRejectsUnionThatDoesNotAcceptEvent(): void
    {
        $attribute = new OnEvent(OnEventSampleEvent::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("must accept '" . OnEventSampleEvent::class . "'");

        $attribute->register(OnEventUnionRejectListener::class, 'onUnion');
    }

    public function testRegisterRejectsIntersectionThatDoesNotAcceptEvent(): void
    {
        $attribute = new OnEvent(OnEventMarkedEvent::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("must accept '" . OnEventMarkedEvent::class . "'");

        $attribute->register(OnEventIntersectionRejectListener::class, 'onIntersection');
    }

    public function testRegisterExceptionCarriesContextForMissingClass(): void
    {
        $attribute = new OnEvent(OnEventSampleEvent::class);

        try {
            $attribute->register('Northrook\\MissingListener', 'onEvent');
            self::fail('Expected InvalidArgumentException.');
        }
        catch (InvalidArgumentException $exception) {
            self::assertSame(
                [
                    'event'  => OnEventSampleEvent::class,
                    'class'  => 'Northrook\\MissingListener',
                    'method' => 'onEvent',
                    'errors' => [],
                ],
                $exception->getContext(),
            );
        }
    }

    public function testRegisterExceptionCarriesContextForInvalidParameter(): void
    {
        $attribute = new OnEvent(OnEventSampleEvent::class);

        try {
            $attribute->register(OnEventBadListeners::class, 'wrongClass');
            self::fail('Expected InvalidArgumentException.');
        }
        catch (InvalidArgumentException $exception) {
            self::assertSame(OnEventSampleEvent::class, $exception->getContext()['event']);
            self::assertSame(OnEventBadListeners::class, $exception->getContext()['class']);
            self::assertSame('wrongClass', $exception->getContext()['method']);
            self::assertSame('event', $exception->getContext()['parameter']);
            self::assertSame(\stdClass::class, $exception->getContext()['type']);
        }
    }

    // ── Reflection discovery ─────────────────────────────────────

    public function testRepeatableAttributeInstancesFromReflection(): void
    {
        $method     = new \ReflectionMethod(OnEventRepeatableListener::class, 'onBoth');
        $attributes = $method->getAttributes(OnEvent::class);

        self::assertCount(2, $attributes);

        $first  = $attributes[0]->newInstance();
        $second = $attributes[1]->newInstance();

        self::assertSame(OnEventSampleEvent::class, $first->event);
        self::assertSame(10, $first->priority);
        self::assertSame(OnEventOtherEvent::class, $second->event);
        self::assertSame(5, $second->priority);
    }

    public function testReflectionAttributesRegisterIndependently(): void
    {
        $method     = new \ReflectionMethod(OnEventRepeatableListener::class, 'onBoth');
        $attributes = $method->getAttributes(OnEvent::class);

        $sample = $attributes[0]
            ->newInstance()
            ->register(
                OnEventRepeatableListener::class,
                'onBoth',
            );
        $other = $attributes[1]
            ->newInstance()
            ->register(
                OnEventRepeatableListener::class,
                'onBoth',
            );

        self::assertSame(OnEventRepeatableListener::class, $sample->class);
        self::assertSame('onBoth', $sample->method);
        self::assertSame(OnEventRepeatableListener::class, $other->class);
        self::assertSame('onBoth', $other->method);
    }
}

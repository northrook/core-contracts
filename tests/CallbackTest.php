<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Callback;
use Northrook\Contracts\Tests\Support\CallbackInvokableFixture;
use Northrook\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CallbackTest extends TestCase
{
    /**
     * @param list<mixed> $boundArgs
     * @param list<mixed> $callArgs
     */
    #[DataProvider('provideFunctionInvocations')]
    public function testFunctionInvocation(
        string $function,
        array  $boundArgs,
        array  $callArgs,
        mixed  $expected,
    ): void {
        $callback = Callback::function(
            $function,
            ...$boundArgs,
        );

        self::assertSame($expected, $callback(...$callArgs));
    }

    /**
     * @return \Generator<string, array{0: string, 1: list<mixed>, 2: list<mixed>, 3: mixed}>
     */
    public static function provideFunctionInvocations(): \Generator
    {
        yield 'bound only' => ['strtoupper', ['abc'], [], 'ABC'];
        yield 'call-time only' => ['strrev', [], ['abc'], 'cba'];
        yield 'bound precedes call-time' => ['substr', ['abcdef', 1], [3], 'bcd'];
        yield 'no args' => ['php_sapi_name', [], [], \PHP_SAPI];
    }
    public function testFunctionWithBoundArgs(): void
    {
        $callback = Callback::function(
            'strtolower',
            'ABC',
        );

        self::assertSame('abc', $callback());
    }

    public function testFunctionWithCallTimeArgs(): void
    {
        $callback = Callback::function(
            'strtolower',
        );

        self::assertSame('xyz', $callback('XYZ'));
    }

    public function testBoundArgsPrecedeCallTimeArgs(): void
    {
        $callback = Callback::function(
            'substr',
            'abcdef',
            1,
        );

        self::assertSame('bc', $callback(2));
    }

    public function testStaticMethod(): void
    {
        $callback = Callback::staticMethod(
            \DateTimeImmutable::class,
            'createFromFormat',
            'Y-m-d',
            '2020-01-01',
        );

        $result = $callback();

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame('2020-01-01', $result->format('Y-m-d'));
    }

    public function testInstantiate(): void
    {
        $callback = Callback::instantiate(\DateTimeImmutable::class, '2020-01-01');

        $result = $callback();

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame('2020-01-01', $result->format('Y-m-d'));
    }

    public function testInvokable(): void
    {
        $callback = Callback::invokable(new CallbackInvokableFixture('pre-'), 'fix');

        self::assertSame('pre-fix-mid', $callback('-mid'));
    }

    public function testUnknownFunctionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown function: no_such_fn_xyz');

        Callback::function(
            'no_such_fn_xyz',
        )();
    }

    public function testUnknownStaticMethodThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DateTime::nope is not callable');

        Callback::staticMethod(\DateTime::class, 'nope')();
    }

    public function testUnknownClassThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown class: NoSuchClassXYZ');

        // @phpstan-ignore-next-line Testing invalid input.
        Callback::instantiate('NoSuchClassXYZ')();
    }

    public function testNonInvokableObjectThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Object is not invokable');

        // @phpstan-ignore-next-line Testing invalid input.
        Callback::invokable(new \stdClass);
    }

    public function testInvokableRejectsClosure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not accept Closure');

        Callback::invokable(static fn(): string => 'nope');
    }

    public function testSerializeFunctionRoundTrip(): void
    {
        $callback = \unserialize(\serialize(Callback::function(
            'strtoupper',
            'hi',
        )));

        self::assertInstanceOf(Callback::class, $callback);
        self::assertSame('HI', $callback());
    }

    public function testSerializeStaticMethodRoundTrip(): void
    {
        $callback = \unserialize(\serialize(
            Callback::staticMethod(
                \DateTimeImmutable::class,
                'createFromFormat',
                'Y-m-d',
                '2021-06-01',
            ),
        ));

        self::assertInstanceOf(Callback::class, $callback);

        $result = $callback();

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame('2021-06-01', $result->format('Y-m-d'));
    }

    public function testSerializeInstantiateRoundTrip(): void
    {
        $callback = \unserialize(\serialize(Callback::instantiate(\stdClass::class)));

        self::assertInstanceOf(Callback::class, $callback);
        self::assertInstanceOf(\stdClass::class, $callback());
    }

    public function testSerializeInvokableRoundTrip(): void
    {
        $callback = \unserialize(\serialize(
            Callback::invokable(new CallbackInvokableFixture('x'), 'y'),
        ));

        self::assertInstanceOf(Callback::class, $callback);
        self::assertSame('xy', $callback());
    }

    public function testClosureCacheClearedOnUnserialize(): void
    {
        $original = Callback::function(
            'strlen',
            'abc',
        );
        self::assertSame(3, $original());

        $restored = \unserialize(\serialize($original));

        self::assertInstanceOf(Callback::class, $restored);
        self::assertSame(3, $restored());
        self::assertSame(3, $restored());
    }

    public function testSerializeExposesDescriptorAndArgs(): void
    {
        $callback = Callback::staticMethod(\DateTimeImmutable::class, 'createFromFormat', 'Y-m-d');

        self::assertSame(
            [
                'descriptor' => [\DateTimeImmutable::class, 'createFromFormat'],
                'args'       => ['Y-m-d'],
            ],
            $callback->__serialize(),
        );
    }

    public function testUnserializeDefaultsMissingArgsToEmptyList(): void
    {
        $callback = new \ReflectionClass(Callback::class)->newInstanceWithoutConstructor();
        $callback->__unserialize(['descriptor' => 'strlen']);

        self::assertInstanceOf(Callback::class, $callback);
        self::assertSame(5, $callback('hello'));
    }

    public function testInvokableWithCallTimeArgsOnly(): void
    {
        $callback = Callback::invokable(new CallbackInvokableFixture('('));

        self::assertSame('(ab)', $callback('a', 'b', ')'));
    }
}

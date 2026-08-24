<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Argument\CallTarget;
use Northrook\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CallTargetTest extends TestCase
{
    public function testObjectStoresClass(): void
    {
        $object = new \DateTimeImmutable;

        $target = new CallTarget($object);

        self::assertNull($target->function);
        self::assertSame(\DateTimeImmutable::class, $target->class);
        self::assertNull($target->method);
    }

    public function testObjectWithMethod(): void
    {
        $target = new CallTarget(new \DateTimeImmutable, 'format');

        self::assertNull($target->function);
        self::assertSame(\DateTimeImmutable::class, $target->class);
        self::assertSame('format', $target->method);
    }

    public function testEmptyMethodBecomesNull(): void
    {
        $target = new CallTarget(\DateTimeImmutable::class, '');

        self::assertSame(\DateTimeImmutable::class, $target->class);
        self::assertNull($target->method);
    }

    public function testInvokableObjectIsClassNotFunction(): void
    {
        $object = new class {
            public function __invoke(): void {}
        };

        $target = new CallTarget($object);

        self::assertNull($target->function);
        self::assertSame($object::class, $target->class);
        self::assertNull($target->method);
    }

    public function testClassString(): void
    {
        $target = new CallTarget(\DateTimeImmutable::class);

        self::assertNull($target->function);
        self::assertSame(\DateTimeImmutable::class, $target->class);
        self::assertNull($target->method);
    }

    public function testClassStringWithMethod(): void
    {
        $target = new CallTarget(\DateTimeImmutable::class, 'format');

        self::assertNull($target->function);
        self::assertSame(\DateTimeImmutable::class, $target->class);
        self::assertSame('format', $target->method);
    }

    public function testClassMethodString(): void
    {
        $target = new CallTarget(\DateTimeImmutable::class . '::format');

        self::assertNull($target->function);
        self::assertSame(\DateTimeImmutable::class, $target->class);
        self::assertSame('format', $target->method);
    }

    public function testNamedFunction(): void
    {
        $target = new CallTarget('strlen');

        self::assertSame('strlen', $target->function);
        self::assertNull($target->class);
        self::assertNull($target->method);
    }

    public function testExplicitMethodForcesClassOnCallableName(): void
    {
        $target = new CallTarget('strlen', 'foo');

        self::assertNull($target->function);
        self::assertSame('strlen', $target->class);
        self::assertSame('foo', $target->method);
    }

    public function testClosureIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CallTarget does not accept Closure.');

        new CallTarget(static fn() => null);
    }

    public function testInvalidClassString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CallTarget class must be a class-string.');

        new CallTarget('not a class');
    }

    public function testClassMethodStringRequiresMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CallTarget string with :: must be class::method.');

        new CallTarget(\DateTimeImmutable::class . '::');
    }

    public function testClassMethodStringRequiresClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CallTarget class must be a class-string.');

        new CallTarget('::format');
    }

    public function testFromObject(): void
    {
        $target = CallTarget::from(new \stdClass);

        self::assertSame(\stdClass::class, $target->class);
        self::assertNull($target->method);
        self::assertNull($target->function);
    }

    public function testFromFunctionString(): void
    {
        $target = CallTarget::from('strtolower');

        self::assertSame('strtolower', $target->function);
        self::assertNull($target->class);
    }

    public function testFromClassMethodString(): void
    {
        $target = CallTarget::from(\DateTimeImmutable::class . '::createFromFormat');

        self::assertSame(\DateTimeImmutable::class, $target->class);
        self::assertSame('createFromFormat', $target->method);
    }

    /**
     * @param array{0: object|class-string, 1?: null|non-empty-string} $value
     */
    #[DataProvider('provideFromArray')]
    public function testFromArray(
        array       $value,
        string      $class,
        null|string $method,
    ): void {
        $target = CallTarget::from($value);

        self::assertNull($target->function);
        self::assertSame($class, $target->class);
        self::assertSame($method, $target->method);
    }

    /**
     * @return \Generator<string, array{0: array{0: object|class-string, 1?: null|non-empty-string}, 1: class-string, 2: null|non-empty-string}>
     */
    public static function provideFromArray(): \Generator
    {
        yield 'class only' => [[\DateTimeImmutable::class], \DateTimeImmutable::class, null];
        yield 'class and method' => [[\DateTimeImmutable::class, 'format'], \DateTimeImmutable::class, 'format'];
        yield 'object and method' => [[new \DateTimeImmutable, 'modify'], \DateTimeImmutable::class, 'modify'];
        yield 'null method' => [[\stdClass::class, null], \stdClass::class, null];
    }

    public function testFromArrayRejectsInvalidTarget(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$value[0] must be an object or class-string.');

        CallTarget::from([1, 'format']);
    }

    public function testFromArrayRejectsInvalidMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$value[1] must be a non-empty method name.');

        CallTarget::from([\DateTimeImmutable::class, 1]);
    }

    public function testFromArrayRejectsEmptyMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('$value[1] must be a non-empty method name.');

        CallTarget::from([\DateTimeImmutable::class, '']);
    }

    public function testValidateAcceptsExistingClass(): void
    {
        $target = new CallTarget(\DateTimeImmutable::class, validate: true);

        self::assertSame(\DateTimeImmutable::class, $target->class);
    }

    public function testValidateAcceptsExistingMethod(): void
    {
        $target = new CallTarget(\DateTimeImmutable::class, 'format', true);

        self::assertSame('format', $target->method);
    }

    public function testValidateAcceptsNamedFunction(): void
    {
        $target = new CallTarget('strlen', validate: true);

        self::assertSame('strlen', $target->function);
    }

    public function testValidateRejectsMissingClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Class 'Northrook\\NoSuchCallTargetClass' does not exist.");

        new CallTarget('Northrook\\NoSuchCallTargetClass', validate: true);
    }

    public function testValidateRejectsMissingMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Method 'noSuchMethod' does not exist on class 'DateTimeImmutable'.");

        new CallTarget(\DateTimeImmutable::class, 'noSuchMethod', true);
    }

    public function testValidateSkippedForMissingClass(): void
    {
        $target = new CallTarget('Northrook\\NoSuchCallTargetClass');

        self::assertSame('Northrook\\NoSuchCallTargetClass', $target->class);
    }
}

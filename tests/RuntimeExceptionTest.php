<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Tests\Support\MixedArray;
use Northrook\RuntimeException;
use PHPUnit\Framework\TestCase;

final class RuntimeExceptionTest extends TestCase
{
    public function testFromMapsNonIntegerCodesToZero(): void
    {
        $foreign = new class('sqlstate failure') extends \Exception {
            /** @var string|int */
            protected $code = '42S22';
        };

        $wrapped = RuntimeException::from($foreign);

        self::assertSame('sqlstate failure', $wrapped->getMessage());
        self::assertSame(0, $wrapped->getCode());
        self::assertSame($foreign, $wrapped->getPrevious());
        self::assertSame('42S22', $foreign->getCode());
    }

    public function testFromMapsDigitStringCodesToZero(): void
    {
        $foreign = new class('integrity constraint') extends \Exception {
            /** @var string|int */
            protected $code = '23000';
        };

        $wrapped = RuntimeException::from($foreign);

        self::assertSame(0, $wrapped->getCode());
        self::assertSame('23000', $wrapped->getPrevious()?->getCode());
    }

    public function testFromPreservesIntegerCodes(): void
    {
        $foreign = new \RuntimeException('boom', 7);

        $wrapped = RuntimeException::from($foreign);

        self::assertSame(7, $wrapped->getCode());
    }

    public function testFromAttachesContext(): void
    {
        $wrapped = RuntimeException::from(new \RuntimeException('boom'), ['origin' => 'test']);

        self::assertSame('test', $wrapped->getContext()['origin']);
    }

    public function testDefaultsToUnspecifiedError(): void
    {
        $exception = new RuntimeException;

        self::assertSame('Unspecified error', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame(['errors' => []], $exception->getContext());
    }

    public function testMessageIsTrimmed(): void
    {
        $exception = new RuntimeException("  padded message\n");

        self::assertSame('padded message', $exception->getMessage());
    }

    public function testMessageDefaultsToPreviousThrowableMessage(): void
    {
        $exception = new RuntimeException(previous: new \LogicException('inherited'));

        self::assertSame('inherited', $exception->getMessage());
        self::assertInstanceOf(\LogicException::class, $exception->getPrevious());
    }

    public function testContextIsDeepFrozenAtConstruction(): void
    {
        $payload        = new \stdClass;
        $payload->value = 'before';

        $exception = new RuntimeException('freeze', ['payload' => $payload]);

        $payload->value = 'after';

        $payload = MixedArray::at($exception->getContext(), 'payload');

        self::assertSame(\stdClass::class, $payload['class']);
        self::assertSame('before', MixedArray::at($payload, 'properties')['value']);
    }

    public function testContextReplacesUnserializableValuesWithDescriptions(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        $exception = new RuntimeException('describe', [
            'closure'  => static fn() => 'unreachable',
            'resource' => $resource,
        ]);

        $context = $exception->getContext();

        self::assertIsString($context['closure']);
        self::assertStringStartsWith('{closure:', $context['closure']);
        self::assertStringContainsString(
            self::class . '::testContextReplacesUnserializableValuesWithDescriptions()',
            $context['closure'],
        );
        self::assertSame('[resource: stream]', $context['resource']);

        \fclose($resource);
    }

    public function testFromMergesExceptionInterfaceContext(): void
    {
        $source = new RuntimeException('source', ['origin' => 'inner']);

        $wrapped = RuntimeException::from($source, ['layer' => 'outer']);

        self::assertSame('inner', $wrapped->getContext()['origin']);
        self::assertSame('outer', $wrapped->getContext()['layer']);
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ErrorBuffer;
use Northrook\Contracts\Exception\RuntimeError;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Tests\Support\MixedArray;
use PHPUnit\Framework\TestCase;

use const Northrook\Contracts\LOG_LEVEL;

final class RuntimeExceptionTest extends TestCase
{
    protected function setUp(): void
    {
        ErrorBuffer::shared()->reset();
        ErrorBuffer::setShared(null);
    }

    protected function tearDown(): void
    {
        ErrorBuffer::shared()->reset();
        ErrorBuffer::setShared(null);
    }

    public function testFromAcceptsStringThrowableCodes(): void
    {
        $foreign = new class('sqlstate failure') extends \Exception {
            /** @var string|int */
            protected $code = '42S22';
        };

        $wrapped = RuntimeException::from($foreign);

        self::assertSame('sqlstate failure', $wrapped->getMessage());
        self::assertSame(0, $wrapped->getCode());
        self::assertSame($foreign, $wrapped->getPrevious());
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

        self::assertSame('test', $wrapped->context['origin']);
    }

    public function testDefaultsToUnspecifiedError(): void
    {
        $exception = new RuntimeException;

        self::assertSame('Unspecified error', $exception->getMessage());
        self::assertSame(LOG_LEVEL['critical'], $exception->getCode());
        self::assertNull($exception->getPrevious());
        self::assertSame([], $exception->context);
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

    public function testPreviousFalseSkipsPreviousChain(): void
    {
        $exception = new RuntimeException(previous: false);

        self::assertNull($exception->getPrevious());
        self::assertSame('Unspecified error', $exception->getMessage());
    }

    public function testContextIsDeepFrozenAtConstruction(): void
    {
        $payload        = new \stdClass;
        $payload->value = 'before';

        $exception = new RuntimeException('freeze', ['payload' => $payload]);

        $payload->value = 'after';

        $payload = MixedArray::at($exception->context, 'payload');

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

        self::assertIsString($exception->context['closure']);
        self::assertStringStartsWith('{closure:', $exception->context['closure']);
        self::assertStringContainsString(
            self::class . '::testContextReplacesUnserializableValuesWithDescriptions()',
            $exception->context['closure'],
        );
        self::assertSame('[resource: stream]', $exception->context['resource']);

        \fclose($resource);
    }

    public function testErrorsFreezeBufferedPhpErrors(): void
    {
        ErrorBuffer::shared()->recordFrom(\E_USER_WARNING, 'buffered one', 'a.php', 1);
        ErrorBuffer::shared()->recordFrom(\E_USER_NOTICE, 'buffered two', 'b.php', 2);

        $exception = new RuntimeException('with errors');

        self::assertCount(2, $exception->errors);
        self::assertContainsOnlyInstancesOf(RuntimeError::class, $exception->errors);
        self::assertSame('buffered one', $exception->errors[0]->message);
        self::assertSame('buffered two', $exception->errors[1]->message);

        ErrorBuffer::shared()->reset();

        self::assertCount(2, $exception->errors);
    }

    public function testErrorsEmptyWhenBufferIsEmpty(): void
    {
        $exception = new RuntimeException('no errors');

        self::assertSame([], $exception->errors);
    }

    public function testErrorsDoNotLeakIntoContext(): void
    {
        ErrorBuffer::shared()->recordFrom(\E_USER_WARNING, 'buffered', 'a.php', 1);

        $exception = new RuntimeException('separation', ['key' => 'value']);

        self::assertSame(['key' => 'value'], $exception->context);
        self::assertCount(1, $exception->errors);
    }
}

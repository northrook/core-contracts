<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ErrorHandler\ErrorBuffer;
use Northrook\Contracts\ErrorHandler\RuntimeError;
use PHPUnit\Framework\TestCase;

final class ErrorBufferTest extends TestCase
{
    private ErrorBuffer $buffer;

    protected function setUp(): void
    {
        ErrorBuffer::setShared($this->buffer = new ErrorBuffer());
    }

    protected function tearDown(): void
    {
        ErrorBuffer::setShared(null);
    }

    public function testRecordAndAll(): void
    {
        $first  = RuntimeError::from(self::sampleArray('first'));
        $second = RuntimeError::from(self::sampleArray('second'));

        $this->buffer->record($first);
        $this->buffer->record($second);

        self::assertSame([$first, $second], $this->buffer->all());
        self::assertSame(2, $this->buffer->count());
    }

    public function testRecordFrom(): void
    {
        $this->buffer->recordFrom(E_USER_NOTICE, 'notice', '/tmp/a.php', 3);

        self::assertCount(1, $this->buffer->all());
        $last = $this->buffer->last();
        if ($last === null) {
            self::fail('Expected a buffered error.');
        }
        self::assertSame(E_USER_NOTICE, $last->type);
        self::assertSame('notice', $last->message);
        self::assertSame('/tmp/a.php', $last->file);
        self::assertSame(3, $last->line);
    }

    public function testAllReturnsExportableRuntimeErrors(): void
    {
        $this->buffer->record(RuntimeError::from(self::sampleArray('one')));
        $this->buffer->record(RuntimeError::from(self::sampleArray('two')));

        self::assertSame([
            self::sampleArray('one'),
            self::sampleArray('two'),
        ], \array_map(
            static fn(RuntimeError $error): array => $error->toArray(),
            $this->buffer->all(),
        ));
    }

    public function testLastReturnsNullWhenEmpty(): void
    {
        self::assertNull($this->buffer->last());
    }

    public function testLastReturnsMostRecentEntry(): void
    {
        $this->buffer->record(RuntimeError::from(self::sampleArray('first')));
        $second = RuntimeError::from(self::sampleArray('second'));
        $this->buffer->record($second);

        self::assertSame($second, $this->buffer->last());
    }

    public function testMarkAndSince(): void
    {
        $this->buffer->record(RuntimeError::from(self::sampleArray('first')));
        $mark   = $this->buffer->mark();
        $second = RuntimeError::from(self::sampleArray('second'));
        $this->buffer->record($second);

        self::assertSame([$second], $this->buffer->since($mark));
        self::assertSame([$second->toArray()], \array_map(
            static fn(RuntimeError $error): array => $error->toArray(),
            $this->buffer->since($mark),
        ));
    }

    public function testSinceTreatsNegativeMarkAsZero(): void
    {
        $first = RuntimeError::from(self::sampleArray('first'));
        $this->buffer->record($first);

        self::assertSame([$first], $this->buffer->since(-1));
    }

    public function testResetClearsBuffer(): void
    {
        $this->buffer->record(RuntimeError::from(self::sampleArray('first')));
        $this->buffer->reset();

        self::assertSame([], $this->buffer->all());
        self::assertSame(0, $this->buffer->count());
        self::assertNull($this->buffer->last());
    }

    public function testSharedReturnsSameInstanceUntilReset(): void
    {
        ErrorBuffer::setShared(null);

        $shared = ErrorBuffer::shared();

        self::assertSame($shared, ErrorBuffer::shared());
    }

    public function testSetSharedReplacesInstance(): void
    {
        $custom = new ErrorBuffer();
        ErrorBuffer::setShared($custom);
        $custom->record(RuntimeError::from(self::sampleArray('custom')));

        self::assertSame($custom, ErrorBuffer::shared());
        self::assertSame('custom', ErrorBuffer::shared()->last()?->message);
    }

    /**
     * @return array{
     *     type: int,
     *     message: string,
     *     file: string,
     *     line: int,
     * }
     */
    private static function sampleArray(
        string $message,
    ): array {
        return [
            'type'    => E_USER_NOTICE,
            'message' => $message,
            'file'    => '/tmp/file.php',
            'line'    => 10,
        ];
    }
}

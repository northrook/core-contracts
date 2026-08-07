<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ErrorBuffer;
use Northrook\Contracts\Exception\RuntimeError;
use PHPUnit\Framework\TestCase;

final class ErrorBufferTest extends TestCase
{
    private ErrorBuffer $buffer;

    protected function setUp(): void
    {
        ErrorBuffer::shared()->reset();
        ErrorBuffer::setShared(null);
        $this->buffer = new ErrorBuffer;
    }

    protected function tearDown(): void
    {
        ErrorBuffer::shared()->reset();
        ErrorBuffer::setShared(null);
    }

    public function testRecordAccumulatesErrors(): void
    {
        self::assertSame([], $this->buffer->all());
        self::assertSame(0, $this->buffer->count());

        $first  = $this->error('first');
        $second = $this->error('second');

        $this->buffer->record($first);
        $this->buffer->record($second);

        self::assertSame([$first, $second], $this->buffer->all());
        self::assertSame(2, $this->buffer->count());
    }

    public function testRecordFromCreatesRuntimeError(): void
    {
        $this->buffer->recordFrom(\E_USER_NOTICE, 'recorded message', 'some/file.php', 42);

        $error = $this->buffer->last();

        self::assertInstanceOf(RuntimeError::class, $error);
        self::assertSame(\E_USER_NOTICE, $error->type);
        self::assertSame('recorded message', $error->message);
        self::assertSame('some/file.php', $error->file);
        self::assertSame(42, $error->line);
        self::assertSame(1, $this->buffer->count());
    }

    public function testAllReturnsExportableRuntimeErrors(): void
    {
        $this->buffer->recordFrom(\E_USER_WARNING, 'export me', __FILE__, __LINE__);

        $exported = $this->buffer->all()[0]->toArray();
        $restored = RuntimeError::from($exported);

        self::assertSame($exported, $restored->toArray());
    }

    public function testLastReturnsNullWhenEmpty(): void
    {
        self::assertNull($this->buffer->last());
    }

    public function testLastReturnsMostRecentEntry(): void
    {
        $this->buffer->record($this->error('older'));
        $recent = $this->error('recent');
        $this->buffer->record($recent);

        self::assertSame($recent, $this->buffer->last());
    }

    public function testMarkAndSinceReturnsEntriesAfterMark(): void
    {
        $this->buffer->record($this->error('before mark'));
        $mark = $this->buffer->mark();

        self::assertSame(1, $mark);

        $after = $this->error('after mark');
        $this->buffer->record($after);
        $this->buffer->record($this->error('also after'));

        $since = $this->buffer->since($mark);

        self::assertSame([$after, $since[1]], $since);
        self::assertCount(2, $since);
        self::assertSame('after mark', $since[0]->message);
    }

    public function testSinceTreatsNegativeMarkAsZero(): void
    {
        $this->buffer->record($this->error('one'));
        $this->buffer->record($this->error('two'));

        self::assertSame($this->buffer->all(), $this->buffer->since(-3));
    }

    public function testSinceBeyondEndReturnsEmptyList(): void
    {
        $this->buffer->record($this->error('only'));

        self::assertSame([], $this->buffer->since(99));
    }

    public function testSinceArraysMapsToErrorArrays(): void
    {
        $this->buffer->recordFrom(\E_USER_WARNING, 'as array', 'file.php', 7);
        $mark = $this->buffer->mark();
        $this->buffer->recordFrom(\E_USER_NOTICE, 'after', 'file.php', 8);

        $arrays = $this->buffer->sinceArrays($mark);

        self::assertCount(1, $arrays);
        self::assertSame(
            [
                'type'    => \E_USER_NOTICE,
                'message' => 'after',
                'file'    => 'file.php',
                'line'    => 8,
            ],
            $arrays[0],
        );
    }

    public function testResetClearsBuffer(): void
    {
        $this->buffer->record($this->error('stale'));

        $this->buffer->reset();

        self::assertSame([], $this->buffer->all());
        self::assertSame(0, $this->buffer->count());
        self::assertNull($this->buffer->last());
    }

    public function testSharedReturnsSameInstance(): void
    {
        $shared = ErrorBuffer::shared();

        self::assertSame($shared, ErrorBuffer::shared());
    }

    public function testSetSharedReplacesInstance(): void
    {
        $replacement = new ErrorBuffer;

        ErrorBuffer::setShared($replacement);

        self::assertSame($replacement, ErrorBuffer::shared());
    }

    public function testSetSharedNullRecreatesInstanceLazily(): void
    {
        $original = ErrorBuffer::shared();

        ErrorBuffer::setShared(null);

        self::assertNotSame($original, ErrorBuffer::shared());
    }

    private function error(
        string $message,
    ): RuntimeError {
        return RuntimeError::from([
            'type'    => \E_USER_WARNING,
            'message' => $message,
            'file'    => __FILE__,
            'line'    => __LINE__,
        ]);
    }
}

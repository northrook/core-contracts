<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Debug\Backtrace;
use Northrook\ErrorHandler\StackFrame;
use PHPUnit\Framework\TestCase;

final class BacktraceTest extends TestCase
{
    public function testCaptureIndexZeroIsCallerFile(): void
    {
        $bt = Backtrace::capture();

        $frame = $bt->frame();

        self::assertInstanceOf(StackFrame::class, $frame);
        self::assertSame(__FILE__, $frame->file);
        self::assertSame(__FUNCTION__, $frame->function);
        self::assertSame(self::class, $frame->class);
        self::assertSame(0, $bt->index());
        self::assertGreaterThan(0, $bt->count());
    }

    public function testUpDownAtClampAndShift(): void
    {
        $bt = $this->fixture([
            ['file' => '/a.php', 'line' => 1, 'function' => 'a'],
            ['file' => '/b.php', 'line' => 2, 'function' => 'b'],
            ['file' => '/c.php', 'line' => 3, 'function' => 'c'],
        ]);

        self::assertSame(0, $bt->index());
        self::assertSame('a', $bt->frame()?->function);

        $up = $bt->up(2);
        self::assertSame(2, $up->index());
        self::assertSame('c', $up->frame()?->function);
        self::assertSame(0, $bt->index());

        $clamped = $up->up(99);
        self::assertSame(2, $clamped->index());

        $down = $up->down(1);
        self::assertSame(1, $down->index());
        self::assertSame('b', $down->frame()?->function);

        $at = $bt->at(1);
        self::assertSame(1, $at->index());

        $under = $bt->down(5);
        self::assertSame(0, $under->index());
    }

    public function testFrameIsLazyCached(): void
    {
        $bt = $this->fixture([
            ['file' => '/a.php', 'line' => 1, 'function' => 'a'],
        ]);

        $first  = $bt->frame();
        $second = $bt->frame();

        self::assertInstanceOf(StackFrame::class, $first);
        self::assertSame($first, $second);
    }

    public function testSourceSkipsBacktraceAndCustomRules(): void
    {
        $bt = $this->fixture([
            [
                'file'     => __FILE__,
                'line'     => 10,
                'function' => 'inner',
                'class'    => Backtrace::class,
            ],
            [
                'file'     => '/vendor/lib/x.php',
                'line'     => 20,
                'function' => 'lib',
                'class'    => 'Vendor\\Lib',
            ],
            [
                'file'     => '/app/User.php',
                'line'     => 30,
                'function' => 'run',
                'class'    => 'App\\User',
            ],
        ]);

        $default = $bt->source();
        self::assertNotNull($default);
        self::assertSame('/vendor/lib/x.php', $default->file);

        $skippedVendor = $bt->source(['/vendor/', 'Vendor\\']);
        self::assertNotNull($skippedVendor);
        self::assertSame('/app/User.php', $skippedVendor->file);
        self::assertSame('App\\User', $skippedVendor->class);
    }

    public function testFindMatchesClassFunctionAndQualified(): void
    {
        $bt = $this->fixture([
            ['file' => '/a.php', 'line' => 1, 'function' => 'boot', 'class' => 'App\\Kernel'],
            ['file' => '/b.php', 'line' => 2, 'function' => 'handle', 'class' => 'App\\Http'],
        ]);

        self::assertSame('boot', $bt->find('App\\Kernel')?->function);
        self::assertSame('App\\Http', $bt->find('handle')?->class);
        self::assertSame(2, $bt->find('App\\Http::handle')?->line);
        self::assertNull($bt->find('missing'));
    }

    public function testFromThrowableUsesExceptionTrace(): void
    {
        try {
            $this->throwForBacktrace();
        } catch (\RuntimeException $e) {
            $bt = Backtrace::from($e);
        }

        self::assertGreaterThan(0, $bt->count());
        $frame = $bt->frame();
        self::assertNotNull($frame);
        self::assertSame('throwForBacktrace', $frame->function);
        self::assertSame(self::class, $frame->class);
        self::assertSame(__FILE__, $frame->file);
    }

    public function testCaptureIgnoresArgsByDefault(): void
    {
        $bt = $this->captureWithArg('secret');

        $raw = $bt->raw()[0] ?? [];

        self::assertArrayNotHasKey('args', $raw);
    }

    public function testIteratorYieldsLazyFrames(): void
    {
        $bt = $this->fixture([
            ['file' => '/a.php', 'line' => 1, 'function' => 'a'],
            ['file' => '/b.php', 'line' => 2, 'function' => 'b'],
        ]);

        $functions = [];
        foreach ($bt as $i => $frame) {
            $functions[$i] = $frame->function;
        }

        self::assertSame([0 => 'a', 1 => 'b'], $functions);
        self::assertSame($bt->frame(0), $bt->frame(0));
    }

    public function testFrameAtOutOfBoundsReturnsNull(): void
    {
        $bt = $this->fixture([
            ['file' => '/a.php', 'line' => 1, 'function' => 'a'],
        ]);

        self::assertNull($bt->frame(99));
    }

    /**
     * @param list<array<string, mixed>> $stack
     */
    private function fixture(
        array $stack,
    ): Backtrace {
        return Backtrace::from($stack);
    }

    private function throwForBacktrace(): never
    {
        throw new \RuntimeException('backtrace fixture');
    }

    private function captureWithArg(
        string $arg,
    ): Backtrace {
        return Backtrace::capture();
    }
}

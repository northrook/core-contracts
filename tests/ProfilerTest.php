<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Profiler;
use Northrook\Profiler\Entry;
use Northrook\Timestamp;
use PHPUnit\Framework\TestCase;

final class ProfilerTest extends TestCase
{
    protected function tearDown(): void
    {
        Profiler::reset();
    }

    public function testResetClearsEntries(): void
    {
        Profiler::register('boot');

        self::assertCount(1, Profiler::getEntries());

        Profiler::reset();

        self::assertSame([], Profiler::getEntries());
    }

    public function testRegisterStoresNameCategoryAndTimestamp(): void
    {
        $before = Timestamp::now();

        Profiler::register('kernel.boot', 'kernel');

        $after = Timestamp::now();
        $entry = Profiler::getEntries()[0];

        self::assertInstanceOf(Entry::class, $entry);
        self::assertSame('kernel.boot', $entry->name);
        self::assertSame('kernel', $entry->category);
        self::assertInstanceOf(Timestamp::class, $entry->timestamp);
        self::assertNotNull($entry->timestamp->precision);
        self::assertGreaterThanOrEqual($before->number, $entry->timestamp->number);
        self::assertLessThanOrEqual($after->number, $entry->timestamp->number);
    }

    public function testRegisterDefaultsCategoryToUncategorized(): void
    {
        Profiler::register('request');

        self::assertSame('uncategorized', Profiler::getEntries()[0]->category);
    }

    public function testRegisterUsesExplicitHrtimeForArgument(): void
    {
        Profiler::register('span', 'test', 9_876_543_210);

        self::assertSame(9_876_543_210, Profiler::getEntries()[0]->hrtime_argument);
    }

    public function testRegisterWithoutHrtimeFallsBackToTimestampPrecision(): void
    {
        Profiler::register('span', 'test');

        $entry = Profiler::getEntries()[0];

        self::assertSame($entry->timestamp->precision, $entry->hrtime_argument);
    }

    public function testRegisterPreservesOrder(): void
    {
        Profiler::register('first', 'a');
        Profiler::register('second', 'b');
        Profiler::register('third', 'c');

        $entries = Profiler::getEntries();

        self::assertSame(['first', 'second', 'third'], \array_map(
            static fn(Entry $entry): string => $entry->name,
            $entries,
        ));
    }
}

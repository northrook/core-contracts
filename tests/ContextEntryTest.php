<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\ColorScheme;
use Northrook\Context\ContextEntry;
use Northrook\Kernel\KernelContext;
use Northrook\Timestamp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextEntryTest extends TestCase
{
    /** @noinspection PhpParamsInspection */
    #[DataProvider('provideContextEnums')]
    public function testConstructSetsKeyContextAndTimestamp(
        \UnitEnum $context,
    ): void {
        $before = Timestamp::now();
        $entry  = new ContextEntry($context);
        $after  = Timestamp::now();

        self::assertSame($context::class, $entry->key);
        self::assertSame($context, $entry->context);
        self::assertInstanceOf(Timestamp::class, $entry->timestamp);
        self::assertNotNull($entry->timestamp->precision);
        self::assertGreaterThanOrEqual($before->number, $entry->timestamp->number);
        self::assertLessThanOrEqual($after->number, $entry->timestamp->number);
    }

    /**
     * @return \Generator<string, array{\UnitEnum}>
     */
    public static function provideContextEnums(): \Generator
    {
        yield 'KernelContext::Boot' => [KernelContext::Boot];
        yield 'KernelContext::Request' => [KernelContext::Request];
        yield 'ColorScheme::Light' => [ColorScheme::Light];
        yield 'ColorScheme::Dark' => [ColorScheme::Dark];
    }

    public function testEachConstructProducesDistinctEntryAndTimestamp(): void
    {
        $first  = new ContextEntry(KernelContext::Boot);
        $second = new ContextEntry(KernelContext::Boot);

        self::assertNotSame($first, $second);
        self::assertNotSame($first->timestamp, $second->timestamp);
        self::assertSame($first->key, $second->key);
        self::assertSame($first->context, $second->context);
    }

    public function testDifferentCasesShareKeyButNotContext(): void
    {
        $boot    = new ContextEntry(KernelContext::Boot);
        $request = new ContextEntry(KernelContext::Request);

        self::assertSame($boot->key, $request->key);
        self::assertSame(KernelContext::class, $boot->key);
        self::assertNotSame($boot->context, $request->context);
    }
}

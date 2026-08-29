<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Resettable;
use Northrook\Profiler\Entry;

final class Profiler implements Resettable
{
    /**
     * @var list<\Northrook\Profiler\Entry>
     */
    private static array $entries = [];

    /**
     * @return \Northrook\Profiler\Entry[]
     */
    public static function getEntries(): array
    {
        return Profiler::$entries;
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string $category
     *  @param null|int        $hrtime
     *
     * @return void
     */
    public static function register(
        string      $name,
        null|string $category = null,
        null|int    $hrtime = null,
    ): void {
        Profiler::$entries[] = new Entry(
            $name,
            $category ?? 'uncategorized',
            Timestamp::now(),
            $hrtime,
        );
    }

    public static function reset(): void
    {
        self::$entries = [];
    }
}

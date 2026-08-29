<?php

declare(strict_types=1);

namespace Northrook\Profiler;

use Northrook\Timestamp;

final readonly class Entry
{
    public int $hrtime_argument;
    public int $hrtime_constructor;
    public int $hrtime_timestamp;

    /**
     * @internal called by the {@see \Northrook\Profiler}
     *
     * @param non-empty-string      $name
     * @param non-empty-string      $category
     * @param \Northrook\Timestamp  $timestamp
     * @param null|int              $hrtime
     */
    public function __construct(
        public string    $name,
        public string    $category,
        public Timestamp $timestamp,
        public null|int  $hrtime = null,
    ) {
        $precision                = \hrtime(true);
        $this->hrtime_constructor = \is_int($precision) ? $precision : (int) $precision;
        $this->hrtime_timestamp   = Timestamp::precision();
        $this->hrtime_argument    = $hrtime ?? $this->timestamp->precision ?? 0;
    }
}

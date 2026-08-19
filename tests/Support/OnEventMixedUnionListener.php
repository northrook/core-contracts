<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class OnEventMixedUnionListener
{
    public function onMixed(
        OnEventSampleEvent|string $event,
    ): void {}
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class OnEventIntersectionListener
{
    public function onIntersection(
        OnEventMarkedEvent&OnEventMarker $event,
    ): void {}
}

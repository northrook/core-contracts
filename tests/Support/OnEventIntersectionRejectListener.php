<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class OnEventIntersectionRejectListener
{
    public function onIntersection(
        OnEventOtherEvent&OnEventMarker $event,
    ): void {}
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class OnEventUnionRejectListener
{
    public function onUnion(
        OnEventOtherEvent|OnEventStandaloneEvent $event,
    ): void {}
}

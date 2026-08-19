<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class OnEventUnionListener
{
    public function onUnion(
        OnEventSampleEvent|OnEventOtherEvent $event,
    ): void {}
}

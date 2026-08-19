<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class OnEventExactListener
{
    public function onExact(
        OnEventSampleEvent $event,
    ): void {}
}

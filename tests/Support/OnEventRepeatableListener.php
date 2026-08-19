<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\EventInterface;
use Northrook\OnEvent;

final class OnEventRepeatableListener
{
    #[OnEvent(OnEventSampleEvent::class, priority: 10)]
    #[OnEvent(OnEventOtherEvent::class, priority: 5)]
    public function onBoth(
        EventInterface $event,
    ): void {}
}

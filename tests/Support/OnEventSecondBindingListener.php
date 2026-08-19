<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class OnEventSecondBindingListener
{
    public function first(
        OnEventSampleEvent $event,
    ): void {}

    public function second(
        OnEventSampleEvent $event,
    ): void {}
}

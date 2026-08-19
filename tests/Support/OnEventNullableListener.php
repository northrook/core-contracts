<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class OnEventNullableListener
{
    public function onNullable(
        null|OnEventSampleEvent $event,
    ): void {}
}

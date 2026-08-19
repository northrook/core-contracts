<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Event;

final class OnEventAbstractListener
{
    public function onAbstract(
        Event $event,
    ): void {}
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\EventInterface;

final class OnEventInterfaceListener
{
    public function onInterface(
        EventInterface $event,
    ): void {}
}

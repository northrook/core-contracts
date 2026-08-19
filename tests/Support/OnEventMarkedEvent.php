<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Event;

/** Sample event implementing the marker for intersection acceptance. */
final class OnEventMarkedEvent extends Event implements OnEventMarker {}

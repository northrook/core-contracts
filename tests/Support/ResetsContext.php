<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Context;
use Northrook\Context\ContextManager;
use Northrook\Logger\Log;

/**
 * Clears the process-local {@see Context} / {@see ContextManager} registry between tests.
 */
final class ResetsContext
{
    public static function reset(): void
    {
        $instance = new \ReflectionProperty(Context::class, 'instance');
        $instance->setValue(null, null);

        $initialized = new \ReflectionProperty(ContextManager::class, 'initialized');
        $initialized->setValue(null, false);

        $logger = new \ReflectionProperty(Log::class, 'logger');
        $logger->setValue(null, null);
    }
}

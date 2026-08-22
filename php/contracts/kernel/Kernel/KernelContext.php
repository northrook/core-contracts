<?php

declare(strict_types=1);

namespace Northrook\Kernel;

use Northrook\Contracts\RuntimeContext;

enum KernelContext implements RuntimeContext
{
    /**
     * Kernel boot.
     */
    case Boot;

    /**
     * Container compilation.
     */
    case Compile;

    /**
     * Initializing the Kernel.
     */
    case Initializing;

    /**
     * CLI, CRON jobs, or other runtime processes.
     */
    case Runtime;

    /**
     * Handling an incoming HTTP request.
     */
    case Request;

    /**
     * Building an HTTP response or payload.
     */
    case Response;

    public function order(): int
    {
        return match ($this) {
            self::Boot => 0,
            self::Compile => 1,
            self::Initializing => 2,
            default => 3,
        };
    }
}

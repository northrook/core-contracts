<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Process probe {@see ContextEnum}.
 *
 * Evaluates via {@see self::resolve()}, not stored context.
 */
interface PlatformContext extends ContextEnum
{
    public function resolve(): bool;
}

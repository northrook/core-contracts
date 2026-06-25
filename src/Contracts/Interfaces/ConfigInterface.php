<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

/**
 * Resolves configuration values into a parameter map.
 *
 * Implementations supply raw config (files, env, etc.) that the container
 * compiler consumes during the discovery phase.
 */
interface ConfigInterface
{
    /**
     * @return array<string, mixed>
     */
    public function resolve(): array;
}

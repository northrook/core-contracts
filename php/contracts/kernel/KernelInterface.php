<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Context\ContextManager;
use Northrook\Contracts\Resettable;

interface KernelInterface extends Resettable
{
    public ContextManager $context { get; }

    public ContainerInterface $container { get; }

    public bool $booted { get; }

    public static function initialize(
        RuntimeOptions $options,
    ): static;

    public function boot(): static;

    /**
     * Runs the Kernel, and returns an Exit Code.
     *
     * Valdiated through {@see \Northrook\RuntimeInterface::validateExitCode()}.
     *
     * @return int<0,254>
     */
    public function run(): int;

    public function reset(): void;
}

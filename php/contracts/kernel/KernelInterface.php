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

    public function run(): int;

    public function reset(): void;
}

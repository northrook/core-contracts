<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Context\ContextManager;

interface KernelInterface
{
    private(set) ContextManager $context { get; }

    public static function initialize(
        Context $context,
    ): static;

    public function boot(): static;

    public function run(): int;

    public function container(): ContainerInterface;
}

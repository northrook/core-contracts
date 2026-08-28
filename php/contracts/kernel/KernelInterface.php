<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Context\ContextManager;
use Northrook\Contracts\Resettable;
use Northrook\Http\RequestInterface;
use Northrook\Http\ResponseInterface;

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

    public function handle(
        RequestInterface $request,
    ): ResponseInterface;

    public function terminate(
        RequestInterface  $request,
        ResponseInterface $response,
    ): void;

    public function reset(): void;
}

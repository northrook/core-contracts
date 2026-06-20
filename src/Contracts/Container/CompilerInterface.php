<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Container;

use Northrook\Contracts\Kernel\OnEvent;

interface CompilerInterface
{
    /**
     * Phase 0 — internal to the compiler implementation.
     *
     * Not accepted by {@see CompilerPass}; runs before any registerable phase.
     * Handles kernel/runtime handover, built-in pass wiring, and cache bootstrap.
     */
    public const string INITIALIZING = 'compiler.initializing';

    // --- Service definitions (registered Autodiscover instances) ---

    /**
     * @template T of object
     *
     * @param Autodiscover<T> $definition
     *
     * @return void
     */
    public function addService(Autodiscover $definition): void;

    public function hasService(string $id): bool;

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return Autodiscover<T>
     */
    public function getService(string $id): Autodiscover;

    /**
     * @param string $role
     *
     * @return list<Autodiscover>
     */
    public function findByRole(string $role): array;

    // --- Parameters (feeds into ParameterStoreInterface internally) ---

    public function setParameter(string $name, mixed $value, bool $secret = false): void;

    /**
     * @param array<string, mixed> $parameters
     */
    public function mergeParameters(array $parameters): void;

    public function hasParameter(string $name): bool;

    public function getParameter(string $name): Parameter;

    // --- Event wiring metadata ---

    /**
     * @param OnEvent $listener
     *
     * @return void
     */
    public function addListener(OnEvent $listener): void;

    /**
     * @return list<OnEvent>
     * @param  ?string       $event
     */
    public function getListeners(null|string $event = null): array;

    // --- Pass registration (called during ingest, executed by compiler impl) ---

    /**
     * @param CompilerPass $pass
     *
     * @return void
     */
    public function addPass(CompilerPass $pass): void;

    // --- Freeze ---

    /**
     * Run {@see INITIALIZING}, then each {@see CompilerPass} phase in order,
     * and freeze the result into a {@see ContainerInterface}.
     *
     * @return ContainerInterface
     */
    public function compile(): ContainerInterface;
}

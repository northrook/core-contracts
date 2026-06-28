<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Northrook\Contracts\Profiler\ProfilerEvent;

/**
 * Records timing events for performance profiling.
 *
 * Returns null from all methods when profiling is disabled, allowing callers
 * to invoke the profiler unconditionally.
 */
interface ProfilerInterface
{
    /**
     * Starts an {@see Event} with the specified `name` and optional `category` and returns it.
     * - The event is started on instantiation.
     * - `null` if the profiler is disabled.
     *
     * @param non-empty-string      $name     the name of the event to retrieve
     * @param null|non-empty-string $category an optional category for the event
     *
     * @return null|ProfilerEvent
     */
    public function __invoke(
        string $name,
        null|string $category = null,
    ): null|ProfilerEvent;

    /**
     * Sets the category for the current instance.
     *
     * @param null|non-empty-string $category
     *
     * @return static
     */
    public function setCategory(
        null|string $category,
    ): static;

    /**
     * Retrieve or create a {@see ProfilerEvent} by `name` and optional `category`
     *
     * @param non-empty-string      $name     name of the event to start
     * @param null|non-empty-string $category optional category name to associate with the event
     *
     * @return null|ProfilerEvent
     */
    public function event(
        string $name,
        null|string $category = null,
    ): null|ProfilerEvent;

    /**
     * Starts an {@see ProfilerEvent} with the specified `name` and optional `category` and returns it.
     *
     *  - The event is started on instantiation.
     *  - `null` if the profiler is disabled.
     *
     * @param non-empty-string      $name     the name of the event to start
     * @param null|non-empty-string $category an optional category for the event
     * @param null|string           $note     an optional note for the `start` record
     *
     * @return null|ProfilerEvent
     */
    public function start(
        string $name,
        null|string $category = null,
        null|string $note = null,
    ): null|ProfilerEvent;

    /**
     * Take a snapshot of current `microtime`
     *
     * @param non-empty-string      $name     the name of the event to start
     * @param null|non-empty-string $category an optional category for the event
     * @param ?string               $note     an optional note
     *
     * @return static
     */
    public function snapshot(
        string $name,
        null|string $category = null,
        null|string $note = null,
    ): static;

    /**
     * Stops an ongoing stopwatch event by name, or all events in the given category.
     *
     * @param null|non-empty-string $name     optional name of the event to stop
     * @param null|non-empty-string $category optional category name to filter or group events
     *
     * @return void
     */
    public function stop(
        null|string $name = null,
        null|string $category = null,
    ): void;

    /**
     * Closes all running events.
     *
     * @return void
     */
    public function close(): void;
}

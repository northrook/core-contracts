<?php

namespace Core\Contracts;

use Core\Contracts\Profiler\ProfilerEvent;

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
        string  $name,
        ?string $category = null,
    ) : ?ProfilerEvent;

    /**
     * Sets the category for the current instance.
     *
     * @param null|non-empty-string $category
     *
     * @return static
     */
    public function setCategory( ?string $category ) : static;

    /**
     * Retrieve or create an {@see ProfilerEvent} by `name` and optional `category`
     *
     * @param non-empty-string      $name     name of the event to start
     * @param null|non-empty-string $category optional category name to associate with the event
     *
     * @return null|ProfilerEvent
     */
    public function event(
        string  $name,
        ?string $category = null,
    ) : ?ProfilerEvent;

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
        string  $name,
        ?string $category = null,
        ?string $note = null,
    ) : ?ProfilerEvent;

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
        string  $name,
        ?string $category = null,
        ?string $note = null,
    ) : static;

    /**
     * Stops an ongoing stopwatch event by name, or all events in the given category.
     *
     * @param null|non-empty-string $name     optional name of the event to stop
     * @param null|non-empty-string $category optional category name to filter or group events
     *
     * @return void
     */
    public function stop(
        ?string $name = null,
        ?string $category = null,
    ) : void;

    /**
     * Closes all running events.
     *
     * @return void
     */
    public function close() : void;
}

<?php

namespace Core\Contracts;

use Core\Contracts\Profiler\Event;

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
     * @return null|Event
     */
    public function __invoke(
        string  $name,
        ?string $category = null,
    ) : ?Event;

    /**
     * Retrieve or create an {@see Event} by `name` and optional `category`
     *
     * @param non-empty-string      $name     name of the event to start
     * @param null|non-empty-string $category optional category name to associate with the event
     *
     * @return null|Event
     */
    public function event(
        string  $name,
        ?string $category = null,
    ) : ?Event;

    /**
     * Starts an {@see Event} with the specified `name` and optional `category` and returns it.
     *
     *  - The event is started on instantiation.
     *  - `null` if the profiler is disabled.
     *
     * @param non-empty-string      $name     the name of the event to start
     * @param null|non-empty-string $category an optional category for the event
     *
     * @return null|Event
     */
    public function start(
        string  $name,
        ?string $category = null,
    ) : ?Event;

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
     * Sets the category for the current instance.
     *
     * @param null|non-empty-string $category
     *
     * @return static
     */
    public function setCategory( ?string $category ) : static;
}

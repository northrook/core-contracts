<?php

declare(strict_types=1);

namespace Northrook\Contracts\Kernel;

use Attribute;
use InvalidArgumentException;

/**
 * Subscribes a method as a listener for a kernel event.
 *
 * Repeatable on a single method to handle multiple event types.
 *
 * The container calls {@see register()} to bind the declaring class and method name after
 * discovering the attribute via reflection.
 *
 * @template T of object = object
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class OnEvent
{
    /** @var class-string<T> */
    public string $class;

    public string $method;

    /**
     * @param class-string<EventInterface> $event
     */
    public function __construct(
        public readonly string $event,
    ) {}

    /**
     * @internal called by the {@see ContainerInterface}
     *
     * @param class-string<T> $class
     *
     * @param string $method
     *
     * @return self<T>
     */
    public function register(
        string $class,
        string $method,
    ): self {
        $this->class = \class_exists($class)
            ? $class
            : throw new InvalidArgumentException(
                $this::class . " cannot register '{$this->event}' on class '{$class}', it does not exist.",
            );

        $this->method = $method;

        return $this;
    }
}

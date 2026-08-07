<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Subscribes a method as a listener for a kernel event.
 *
 * Repeatable on a single method to handle multiple event types.
 *
 * Listener signature:
 * - Argument `0` is always the event instance (`{@see $event}` or a parent type
 *   that accepts it).
 * - Further arguments are optional and resolved by the container (type-hint and/or
 *   {@see Autowire}), including when the listener is a discovered `__invoke`.
 *
 * The container calls {@see register()} to bind the declaring class and method name after
 * discovering the attribute via reflection during compiler discovery.
 * Listener order uses {@see $priority} (higher first; see {@see ListenerMap::sorted()}).
 *
 * Dispatch: {@see EventDispatcherInterface} invokes provider callables with the event as
 * argument `0`; further arguments are container-resolved. Full param wiring is validated
 * during container compilation.
 *
 * @template T of object = object
 *
 * @example
 *  #[OnEvent(FailedLogin::class)]
 *  public function onFail(FailedLogin $event, LoggerInterface $logger): void {}
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class OnEvent
{
    /** @var class-string<T> */
    public string $class;

    public string $method;

    /**
     * @param class-string<EventInterface> $event
     * @param int                          $priority  Listener order hint for the container
     *
     * @throws InvalidArgumentException When `$event` does not implement {@see EventInterface}
     */
    public function __construct(
        public readonly string $event,
        public readonly int    $priority = 0,
    ) {
        if (! \is_a($event, EventInterface::class, true)) {
            throw new InvalidArgumentException(
                message: "OnEvent event '{$event}' must implement " . EventInterface::class . '.',
                context: ['event' => $event],
            );
        }
    }

    /**
     * @param class-string<T> $class
     * @param string          $method
     *
     * @return self<T>
     *
     * @throws InvalidArgumentException When `$class` is missing, `$method` is not a public
     *                                  instance method, the first parameter cannot accept
     *                                  {@see $event}, or a conflicting re-bind is attempted
     *
     * @used-by {@see ContainerInterface}
     */
    public function register(
        string $class,
        string $method,
    ): self {
        if (isset($this->class, $this->method)) {
            if ($this->class === $class && $this->method === $method) {
                return $this;
            }

            throw new InvalidArgumentException(
                message: $this::class . " already registered on '{$this->class}::{$this->method}'.",
                context: [
                    'event'  => $this->event,
                    'class'  => $class,
                    'method' => $method,
                    'bound'  => "{$this->class}::{$this->method}",
                ],
            );
        }

        if (! \class_exists($class)) {
            throw new InvalidArgumentException(
                message: $this::class . " cannot register '{$this->event}' on class '{$class}', it does not exist.",
                context: [
                    'event'  => $this->event,
                    'class'  => $class,
                    'method' => $method,
                ],
            );
        }

        try {
            $reflection = new \ReflectionMethod($class, $method);
        } catch (\ReflectionException) {
            throw new InvalidArgumentException(
                message: $this::class
                . " cannot register '{$this->event}' on '{$class}::{$method}', method does not exist.",
                context: [
                    'event'  => $this->event,
                    'class'  => $class,
                    'method' => $method,
                ],
            );
        }

        if (! $reflection->isPublic() || $reflection->isStatic()) {
            throw new InvalidArgumentException(
                message: $this::class
                . " cannot register '{$this->event}' on '{$class}::{$method}', listener must be a public instance method.",
                context: [
                    'event'  => $this->event,
                    'class'  => $class,
                    'method' => $method,
                ],
            );
        }

        $this->assertEventParameter($reflection, $class, $method);

        $this->class  = $class;
        $this->method = $method;

        return $this;
    }

    /**
     * @param class-string $class
     */
    private function assertEventParameter(
        \ReflectionMethod $reflection,
        string            $class,
        string            $method,
    ): void {
        $parameters = $reflection->getParameters();

        if ($parameters === []) {
            throw new InvalidArgumentException(
                message: $this::class . " listener '{$class}::{$method}' must declare the event as parameter 0.",
                context: [
                    'event'  => $this->event,
                    'class'  => $class,
                    'method' => $method,
                ],
            );
        }

        $eventParameter = $parameters[0];
        $type           = $eventParameter->getType();

        if ($type === null || ! $this->typeAcceptsEvent($type)) {
            $label = $type instanceof \ReflectionNamedType
                ? $type->getName()
                : (string) $type;

            throw new InvalidArgumentException(
                message: $this::class
                . " listener '{$class}::{$method}' parameter 0 must accept '{$this->event}'"
                . ( $label !== '' ? ", got '{$label}'." : '.' ),
                context: [
                    'event'     => $this->event,
                    'class'     => $class,
                    'method'    => $method,
                    'parameter' => $eventParameter->getName(),
                    'type'      => $label !== '' ? $label : null,
                ],
            );
        }
    }

    private function typeAcceptsEvent(
        \ReflectionType $type,
    ): bool {
        if ($type instanceof \ReflectionUnionType) {
            return \array_any(
                array   : $type->getTypes(),
                callback: $this->typeAcceptsEvent(...),
            );
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return \array_all(
                array   : $type->getTypes(),
                callback: $this->typeAcceptsEvent(...),
            );
        }

        if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        // Param type must accept an instance of {@see $event}.
        return \is_a($this->event, $type->getName(), true);
    }
}

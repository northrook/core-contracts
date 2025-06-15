<?php

namespace Core\Contracts\Kernel;

use Attribute;
use InvalidArgumentException;

/**
 * @template T of object
 */
#[Attribute( Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE )]
final readonly class OnEvent
{
    /** @var class-string<T> */
    public readonly string $class;

    public readonly string $method;

    /**
     * @param class-string<EventInterface> $event
     */
    public function __construct( public readonly string $event ) {}

    /**
     * @internal called by the {@see ContainerInterface}
     *
     * @param class-string<T> $class
     *
     * @param string $method
     *
     * @return self<T>
     */
    public function register( string $class, string $method ) : self
    {
        $this->class = \class_exists( $class )
                ? $class
                : throw new InvalidArgumentException(
                    $this::class." cannot register '{$this->event}' on class '{$class}', it does not exist.",
                );

        $this->method = $method;

        return $this;
    }
}

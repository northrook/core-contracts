<?php

declare(strict_types=1);

namespace Core\Contracts\Container\Compiler;

use Attribute;
use Core\Contracts\Container\CompilerInterface;
use InvalidArgumentException;

/**
 * @template T of \Core\Contracts\Container\CompilerInterface
 */
#[Attribute( Attribute::TARGET_CLASS )]
final readonly class CompilerPass
{
    /** @var class-string<T> */
    public string $class;

    public ?CompilerInterface $instance;

    /**
     * @param null|int                $priority  lower executed first
     * @param CompilerInterface::*    $pass
     * @param array<array-key, mixed> $arguments
     */
    public function __construct(
        public ?int   $priority = null,
        public string $pass = CompilerInterface::PARSE,
        public array  $arguments = [],
    ) {}

    public function __invoke() : CompilerInterface
    {
        return $this->instance ?? new $this->class( ...$this->arguments );
    }

    /**
     * @internal called by the {@see CompilerInterface}
     *
     * @param class-string<T>    $class
     * @param ?CompilerInterface $compiler
     *
     * @return self<T>
     */
    final public function register( string $class, ?CompilerInterface $compiler = null ) : self
    {
        $this->class = \class_exists( $class )
                ? $class
                : throw new InvalidArgumentException(
                    $this::class." cannot register '{$class}', it does not exist.",
                );

        $this->instance = $compiler;

        return $this;
    }
}

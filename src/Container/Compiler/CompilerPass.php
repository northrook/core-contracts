<?php

declare(strict_types=1);

namespace Core\Contracts\Container\Compiler;

use Attribute;
use InvalidArgumentException;

/**
 * @template T of CompilerPassInterface
 */
#[Attribute( Attribute::TARGET_CLASS )]
final readonly class CompilerPass
{
    /** @var class-string<T> */
    public string $compilerPass;

    /**
     * @param null|int                 $priority  lower executed first
     * @param CompilerPassInterface::* $pass
     * @param array<array-key, mixed>  $arguments
     */
    public function __construct(
        public ?int   $priority = null,
        public string $pass = CompilerPassInterface::PARSE,
        public array  $arguments = [],
    ) {}

    /**
     * @internal called by the {@see CompilerInterface}
     *
     * @param class-string<T> $class
     *
     * @return self<T>
     */
    final public function register( string $class ) : self
    {
        $this->compilerPass = \class_exists( $class )
                ? $class
                : throw new InvalidArgumentException(
                    $this::class." cannot register '{$class}', it does not exist.",
                );

        return $this;
    }
}

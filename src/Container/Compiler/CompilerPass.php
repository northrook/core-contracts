<?php

declare(strict_types=1);

namespace Core\Contracts\Container\Compiler;

use Attribute;
use InvalidArgumentException;

/**
 * @template T of object
 */
#[Attribute( Attribute::TARGET_CLASS )]
final readonly class CompilerPass
{
    /**
     * # 1
     * First pass.
     *  - Resolve {@see ConfigInterface}s
     * - {@see Autodiscover} services
     * - {@see Autowire} dependencies
     */
    public const string DISCOVERY = 'compiler.discovery';

    /**
     * # 2
     * Modify discovered {@see Compiler} arguments
     */
    public const string PARSE = 'compiler.parse';

    /** # 3
     * Normalize {@see Parameters} by context
     */
    public const string OPTIMIZE = 'compiler.optimize';

    /**
     * # 4
     * Final pass
     * - Validating {@see ConfigInterface} values
     * - Ensures required {@see Services} and {@see Parameters} are set
     */
    public const string VALIDATE = 'compiler.validate';

    /** @var class-string<T> */
    public string $compilerPass;

    /**
     * @param null|int                $priority  lower executed first
     * @param self::*                 $type
     * @param array<array-key, mixed> $arguments
     */
    public function __construct(
        public ?int   $priority = null,
        public string $type = CompilerPass::PARSE,
        public array  $arguments = [],
    ) {}

    /**
     * @internal called by the {@see CompilerInterface}
     *
     * @param class-string<T> $class
     * @param ?string         $id
     *
     * @return self<T>
     */
    final public function register( string $class, ?string $id = null ) : self
    {
        $this->compilerPass = \class_exists( $class )
                ? $class
                : throw new InvalidArgumentException(
                    $this::class." cannot register '{$class}', it does not exist.",
                );

        return $this;
    }
}

<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Container;

use Attribute;
use InvalidArgumentException;

use const Northrook\Contracts\AUTO;

/**
 * Registers a compiler pass against a pipeline phase.
 *
 * Pass classes are invoked during container compilation to transform service
 * definitions. {@see register()} binds the pass class after reflection
 * discovers the attribute on a service.
 *
 * @template T of CompilerPassInterface = CompilerPassInterface
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class CompilerPass
{
    /** @var array<int, string> */
    private const array PASSES = [
        CompilerPass::DISCOVERY,
        CompilerPass::PARSE,
        CompilerPass::OPTIMIZE,
        CompilerPass::VALIDATE,
    ];

    /**
     * # 1 - First pass
     *
     * - Resolve {@see ConfigInterface}s
     * - {@see Autodiscover} services
     * - {@see Autowire} dependencies
     */
    public const string DISCOVERY = 'compiler.discovery';

    /**
     * # 2
     *
     *  - Modify discovered {@see CompilerPassInterface} arguments
     */
    public const string PARSE = 'compiler.parse';

    /**
     * # 3
     *
     * - Normalize {@see Parameters} by context
     */
    public const string OPTIMIZE = 'compiler.optimize';

    /**
     * # 4 - Final pass
     *
     * - Validating {@see CompilerPassInterface} values
     * - Ensures required {@see Services} and {@see Parameters} are set
     */
    public const string VALIDATE = 'compiler.validate';

    /** @var class-string<T> */
    public private(set) string $class;

    public string $pass {
        get => $this->pass;
        set => $this->pass = \in_array($value, self::PASSES)
            ? $value
            : throw new InvalidArgumentException(
                "`{$value}` is not a valid compiler pass. Must be one of: `" . \implode('`, `', self::PASSES) . '`',
            );
    }

    /**
     * Lower executed first.
     *
     * {@see AUTO} uses next available priority
     * - Must be between `-1_024` and `1_024`.
     *
     * @var null|int
     */
    public null|int $priority {
        get => $this->priority ?? null;
        set {
            if ($value === null) {
                $this->priority = null;

                return;
            }

            if ($value < -1_024 || $value > 1_024) {
                throw new InvalidArgumentException(
                    "CompilerPass priority must be between `-1_024` and `1_024`, `{$value}` given.",
                );
            }

            $this->priority = $value;
        }
    }

    /** @var T */
    public CompilerPassInterface $instance {
        get {
            if (! isset($this->instance)) {
                if (! isset($this->class)) {
                    throw new InvalidArgumentException(
                        $this::class . " cannot create an instance of '{$this->class}', call register() first.",
                    );
                }

                $this->instance = new $this->class(...$this->arguments);
            }

            return $this->instance;
        }
    }

    /**
     * @param null|int                       $priority  lower executed first
     * @param value-of<CompilerPass::PASSES> $pass
     * @param array<array-key, mixed>        $arguments
     */
    public function __construct(
        null|int $priority = AUTO,
        string $pass = CompilerPass::PARSE,
        public readonly array $arguments = [],
    ) {
        $this->priority = $priority;
        $this->pass     = $pass;
    }

    /**
     * @return T
     */
    public function __invoke(): CompilerPassInterface
    {
        return $this->instance;
    }

    /**
     * Called by the {@see CompilerInterface}
     *
     * @param class-string<T> $className
     *
     * @return self<T>
     */
    final public function register(string $className): self
    {
        $this->class = \class_exists($className)
            ? $className
            : throw new InvalidArgumentException($this::class . " cannot register '{$className}', it does not exist.");

        return $this;
    }
}

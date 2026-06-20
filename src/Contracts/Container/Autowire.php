<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Container;

use Attribute;

/**
 * Marks a method for dependency injection by the container.
 *
 * Only container initialization should invoke annotated setters; they are not
 * intended for manual calls.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Autowire
{
    /** @var array<array-key, mixed> */
    public array $arguments;

    /**
     * Optional `$arguments` can be passed to the method.
     *
     * Use this to supply values that cannot be autowired by the container.
     *
     * Typically used for scalar values or objects not managed by the container.
     *
     * @param mixed ...$arguments
     */
    public function __construct(mixed ...$arguments)
    {
        $this->arguments = $arguments;
    }
}

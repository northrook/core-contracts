<?php

namespace Core\Contracts\Container;

use Attribute;

/**
 * Autowire-only setter method.
 *
 * - Called by the Container during initialization.
 * - This method should not be invoked manually.
 */
#[Attribute( Attribute::TARGET_METHOD )]
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
    public function __construct( mixed ...$arguments )
    {
        $this->arguments = $arguments;
    }
}

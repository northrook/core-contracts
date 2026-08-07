<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container;

enum CompilerPass: string
{
    /**
     * # `0`
     *
     * Earliest step, Kernel is booted, {@see \Northrook\Contracts\CompilerInterface} is created.
     */
    case INITIALIZATION = 'compiler.initialization';

    /**
     * # `1` - First mutable pass
     *
     * - Resolve {@see \Northrook\Contracts\ConfigObject}s
     * - {@see \Northrook\Contracts\Service\Autodiscover} services
     * - {@see \Northrook\Contracts\Autowire} dependencies
     * - Collect {@see \Northrook\Contracts\OnEvent} listeners during discovery
     */
    case DISCOVERY = 'compiler.discovery';

    /**
     * # `2`
     *
     * - Modify discovered {@see CompilerPassInterface} arguments
     */
    case PARSE = 'compiler.parse';

    /**
     * # `3`
     *
     * - Normalize {@see \Northrook\Contracts\ParameterStoreInterface} by context
     */
    case OPTIMIZE = 'compiler.optimize';

    /**
     * # `4` - Final mutable pass
     *
     * - Validating {@see CompilerPassInterface} values
     * - Ensures required {@see \Northrook\Contracts\ServiceDefinition}s and
     *   {@see \Northrook\Contracts\ParameterStoreInterface} entries are set
     * - Validates every {@see \Northrook\Contracts\Service\Tag} on each definition:
     *   `$arguments` must be compatible with that service’s constructor or static
     *   factory signature (named keys may omit parameters; positional must align)
     */
    case VALIDATE = 'compiler.validate';

    /**
     * # `5` - Compile
     *
     * Freeze into an immutable {@see \Northrook\Contracts\ContainerInterface}.
     *
     * Optional: OPCache / ahead-of-time dump of the compiled container.
     */
    case COMPILE = 'compiler.compile';
}

<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\Contracts\RuntimeContext;

enum CompilerPass: string implements RuntimeContext
{
    /**
     * # `0`
     *
     * Earliest step, Kernel is booted, {@see CompilerInterface} is created.
     */
    case INITIALIZATION = 'compiler.initialization';

    /**
     * # `1` - First mutable pass
     *
     * - Resolve {@see \Northrook\ConfigObject}s
     * - {@see \Northrook\Container\Service\Autodiscover} services
     * - {@see \Northrook\Container\Autowire} dependencies
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
     * - Normalize {@see \Northrook\ParameterStoreInterface} by context
     */
    case OPTIMIZE = 'compiler.optimize';

    /**
     * # `4` - Final mutable pass
     *
     * - Validating {@see CompilerPassInterface} values
     * - Ensures required {@see \Northrook\Container\ServiceDefinition}s and
     *   {@see \Northrook\ParameterStoreInterface} entries are set
     * - Validates every {@see \Northrook\Container\Service\Tag} on each definition:
     *   `$arguments` must be compatible with that service’s constructor or static
     *   factory signature (named keys may omit parameters; positional must align)
     */
    case VALIDATE = 'compiler.validate';

    /**
     * # `5` - Compile
     *
     * Freeze into an immutable {@see \Northrook\ContainerInterface}.
     *
     * Optional: OPCache / ahead-of-time dump of the compiled container.
     */
    case COMPILE = 'compiler.compile';
}

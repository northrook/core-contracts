<?php

namespace Core\Contracts\Container;

interface CompilerInterface
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
     * Modify discovered {@see CompilerInterface} arguments
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
}

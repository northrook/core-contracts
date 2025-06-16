<?php

namespace Core\Contracts\Container\Compiler;

use Core\Contracts\Container\CompilerInterface;

interface CompilerPassInterface
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

    /**
     * Processes the given compiler instance.
     *
     * @internal called when compiling the {@see ContainerInterface}
     *
     * @param CompilerInterface        $compiler
     * @param CompilerPassInterface::* $pass
     *
     * @return void
     */
    public function process(
        CompilerInterface $compiler,
        string            $pass,
    ) : void;
}

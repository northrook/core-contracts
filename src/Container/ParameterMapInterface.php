<?php

namespace Core\Contracts\Container;

use Psr\Container\NotFoundExceptionInterface;

interface ParameterMapInterface
{
    /**
     * Returns true if a parameter name is defined.
     *
     * @param non-empty-string $parameter
     */
    public function has( string $parameter ) : bool;

    /**
     * @param non-empty-string $parameter
     * @param mixed            $default
     *
     * @throws NotFoundExceptionInterface
     */
    public function get( string $parameter, mixed $default ) : Parameter;

    /**
     * @return array<non-empty-string, Parameter>
     */
    public function all() : array;
}

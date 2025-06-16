<?php

namespace Core\Contracts;

interface ConfigInterface
{
    /**
     * Can provide default named arguments.
     *
     * @return static
     */
    public static function config() : static;

    /**
     * @return array<string, mixed>
     */
    public function resolve() : array;
}

<?php

namespace Core\Contracts;

interface ConfigInterface
{
    /**
     * @return array<string, mixed>
     */
    public function resolve() : array;
}

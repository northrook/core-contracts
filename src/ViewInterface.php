<?php

namespace Core\Contracts;

use Stringable;

interface ViewInterface extends Stringable
{
    /**
     * Return a {@see ViewInterface} as {@see Stringable}.
     *
     * @return Stringable
     */
    public function getView() : Stringable;
}

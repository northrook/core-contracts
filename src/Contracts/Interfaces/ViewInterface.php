<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Stringable;

interface ViewInterface extends Stringable
{
    /**
     * Return a {@see ViewInterface} as {@see Stringable}.
     *
     * @return Stringable
     */
    public function getView(): Stringable;
}

<?php

declare(strict_types=1);

namespace Northrook;

interface ViewInterface
{
    /**
     * Renders the view.
     *
     * @return null|non-empty-string|\Stringable `null` if the view is empty
     */
    public function render(): null|string|\Stringable;
}

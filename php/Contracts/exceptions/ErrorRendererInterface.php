<?php

declare(strict_types=1);

namespace Northrook\Contracts;

interface ErrorRendererInterface
{
    public function render(
        ErrorReport $report,
    ): string;
}

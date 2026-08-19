<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\ErrorHandler\ErrorReport;

interface ErrorRendererInterface
{
    public function render(
        ErrorReport $report,
    ): string;
}

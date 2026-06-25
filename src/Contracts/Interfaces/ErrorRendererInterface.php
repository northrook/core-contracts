<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Northrook\Contracts\ErrorHandler\ErrorReport;

interface ErrorRendererInterface
{
    public function render(ErrorReport $report): string;

    public function supports(ErrorReport $report): bool;
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Northrook\Contracts\Assets\AssetCollection;
use Northrook\Contracts\RenderStrategy;

interface AssetProviderInterface
{
    public RenderStrategy $renderStrategy { get; }

    public function getAssets(): AssetCollection;
}

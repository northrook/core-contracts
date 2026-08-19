<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Assets\AssetCollection;
use Northrook\Assets\RenderStrategy;

interface AssetProviderInterface
{
    public RenderStrategy $renderStrategy { get; }

    public function getAssets(): AssetCollection;
}

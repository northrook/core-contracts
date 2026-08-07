<?php

declare(strict_types=1);

namespace Northrook\Contracts;

interface AssetProviderInterface
{
    public RenderStrategy $renderStrategy { get; }

    public function getAssets(): AssetCollection;
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Contracts\AssetCollection;
use Northrook\Contracts\AssetInterface;
use Northrook\Contracts\AssetProviderInterface;
use Northrook\Contracts\RenderStrategy;

/**
 * Minimal {@see AssetProviderInterface} fixture for `AssetsTest`.
 */
final class AssetsTestProviderStub implements AssetProviderInterface
{
    private readonly AssetCollection $assets;

    public function __construct(
        public readonly RenderStrategy $renderStrategy,
        AssetInterface ...             $assets,
    ) {
        $this->assets = new AssetCollection(...$assets);
    }

    public function getAssets(): AssetCollection
    {
        return $this->assets;
    }
}

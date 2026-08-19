<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\AssetInterface;
use Northrook\Assets\AssetOrigin;
use Northrook\Assets\AssetType;

/**
 * Minimal {@see AssetInterface} fixture for `AssetsTest`.
 */
readonly class AssetsTestStubAsset implements AssetInterface
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $hash
     */
    public function __construct(
        public string      $id,
        public AssetType   $type = AssetType::Style,
        public AssetOrigin $origin = AssetOrigin::Path,
        public string      $hash = 'hash',
        public string      $value = 'asset.css',
    ) {}
}

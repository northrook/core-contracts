<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Contracts\AssetInterface;
use Northrook\Contracts\AssetOrigin;
use Northrook\Contracts\AssetType;

/**
 * Second {@see AssetInterface} fixture; used for `AssetCollection::get()` filtering.
 */
readonly class AssetsTestOtherStubAsset implements AssetInterface
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $hash
     */
    public function __construct(
        public string      $id,
        public AssetType   $type = AssetType::Script,
        public AssetOrigin $origin = AssetOrigin::Url,
        public string      $hash = 'hash',
        public string      $value = 'asset.js',
    ) {}
}

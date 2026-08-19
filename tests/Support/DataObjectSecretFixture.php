<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Assets\AssetType;
use Northrook\Container\Secret;
use Northrook\DataObject;
use Northrook\Timestamp;

final readonly class DataObjectSecretFixture extends DataObject
{
    public function __construct(
        #[Secret]
        public Timestamp $secretTimestamp,
        #[Secret]
        public AssetType $secretEnum,
        public Timestamp $visibleTimestamp,
        public AssetType $visibleEnum,
    ) {
        parent::__construct();
    }
}

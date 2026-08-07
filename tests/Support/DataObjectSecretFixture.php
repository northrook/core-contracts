<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Contracts\AssetType;
use Northrook\Contracts\DataObject;
use Northrook\Contracts\Secret;
use Northrook\Contracts\Timestamp;

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

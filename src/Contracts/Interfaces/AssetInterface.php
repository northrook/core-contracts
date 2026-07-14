<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

use Northrook\Contracts\Assets\AssetOrigin;
use Northrook\Contracts\Assets\AssetType;

interface AssetInterface
{
    public AssetType $type { get; }

    public AssetOrigin $origin { get; }

    /**
     * Unique identifier for the asset.
     *
     * @var non-empty-string
     */
    public string $id { get; }

    /**
     * A hash of the resolved payload.
     *
     * @var non-empty-string
     */
    public string $hash { get; }

    /**
     * Payload keyed by {@see AssetOrigin}: path, URL, or raw data.
     */
    public string $value { get; }
}

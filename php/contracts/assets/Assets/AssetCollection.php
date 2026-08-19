<?php

declare(strict_types=1);

namespace Northrook\Assets;

use Northrook\AssetInterface;
use Northrook\DataObject;
use Northrook\RuntimeException;

/**
 * @implements \IteratorAggregate<string, AssetInterface>
 */
final readonly class AssetCollection extends DataObject implements \Countable, \IteratorAggregate
{
    /**
     * @var array<string, AssetInterface>
     */
    public array $assets;

    public function __construct(
        AssetInterface ...$assets,
    ) {
        $this->assets = $this->resolve($assets);
        parent::__construct();
    }

    /**
     * @template T of AssetInterface
     *
     * @param class-string<T>  $assetClass
     *
     * @return T[]
     */
    public function get(
        string $assetClass,
    ): array {
        return \array_values(\array_filter(
            $this->assets,
            static fn(AssetInterface $asset): bool => $asset instanceof $assetClass,
        ));
    }

    /**
     * @return \Traversable<string, AssetInterface>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->assets;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return \count($this->assets);
    }

    /**
     * @param AssetInterface[] $assets
     *
     * @return array<non-empty-string, AssetInterface>
     */
    private function resolve(
        array $assets,
    ): array {
        /** @var array<non-empty-string, AssetInterface> $array */
        $array = [];

        foreach ($assets as $asset) {
            if (isset($array[$asset->id])) {
                throw new RuntimeException(
                    message: 'Duplicate asset ID: ' . $asset->id,
                    context: [
                        'assets'    => $assets,
                        'resolving' => $asset,
                        'duplicate' => $array[$asset->id],
                    ],
                );
            }

            $array[$asset->id] = $asset;
        }

        return $array;
    }
}

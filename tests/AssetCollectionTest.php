<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Assets\AssetCollection;
use Northrook\Contracts\Assets\AssetOrigin;
use Northrook\Contracts\Assets\AssetType;
use Northrook\Contracts\ErrorHandler\ErrorBuffer;
use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\Interfaces\AssetInterface;
use PHPUnit\Framework\TestCase;

final class AssetCollectionTest extends TestCase
{
    protected function setUp(): void
    {
        error_clear_last();
        ErrorBuffer::shared()->reset();
    }

    protected function tearDown(): void
    {
        error_clear_last();
        ErrorBuffer::shared()->reset();
    }

    public function testKeysAssetsById(): void
    {
        $style  = new AssetCollectionStubAsset(id: 'pkg.style');
        $script = new AssetCollectionStubAsset(id: 'pkg.script');

        $collection = new AssetCollection($style, $script);

        self::assertSame(
            [
                'pkg.style'  => $style,
                'pkg.script' => $script,
            ],
            $collection->assets,
        );
    }

    public function testRejectsDuplicateIds(): void
    {
        $first  = new AssetCollectionStubAsset(id: 'pkg.style');
        $second = new AssetCollectionStubAsset(
            id: 'pkg.style',
            value: 'other.css',
        );

        try {
            new AssetCollection($first, $second);
            self::fail('Expected RuntimeException for duplicate asset ID.');
        } catch (RuntimeException $exception) {
            self::assertSame('Duplicate asset ID: pkg.style', $exception->getMessage());

            self::assertIsArray($exception->context['assets']);
            self::assertCount(2, $exception->context['assets']);
            self::assertInstanceOf(AssetCollectionStubAsset::class, $exception->context['assets'][0]);
            self::assertInstanceOf(AssetCollectionStubAsset::class, $exception->context['assets'][1]);
            self::assertNotSame($first, $exception->context['assets'][0]);
            self::assertNotSame($second, $exception->context['assets'][1]);
            self::assertSame('asset.css', $exception->context['assets'][0]->value);
            self::assertSame('other.css', $exception->context['assets'][1]->value);

            self::assertInstanceOf(AssetCollectionStubAsset::class, $exception->context['resolving']);
            self::assertNotSame($second, $exception->context['resolving']);
            self::assertSame('other.css', $exception->context['resolving']->value);

            self::assertInstanceOf(AssetCollectionStubAsset::class, $exception->context['duplicate']);
            self::assertNotSame($first, $exception->context['duplicate']);
            self::assertSame('asset.css', $exception->context['duplicate']->value);
        }
    }

    public function testGetFiltersByClass(): void
    {
        $style = new AssetCollectionStubAsset(id: 'pkg.style');
        $other = new AssetCollectionOtherStubAsset(id: 'pkg.other');

        $collection = new AssetCollection($style, $other);

        self::assertSame([$style], $collection->get(AssetCollectionStubAsset::class));
        self::assertSame([$other], $collection->get(AssetCollectionOtherStubAsset::class));
        self::assertSame([$style, $other], $collection->get(AssetInterface::class));
        self::assertSame([], new AssetCollection($other)->get(AssetCollectionStubAsset::class));
    }

    public function testCountAndIterationMatchAssets(): void
    {
        $style  = new AssetCollectionStubAsset(id: 'pkg.style');
        $script = new AssetCollectionStubAsset(id: 'pkg.script');

        $collection = new AssetCollection($style, $script);

        self::assertCount(2, $collection);
        self::assertSame(
            [
                'pkg.style'  => $style,
                'pkg.script' => $script,
            ],
            \iterator_to_array($collection),
        );
    }

    public function testEmptyCollection(): void
    {
        $collection = new AssetCollection();

        self::assertSame([], $collection->assets);
        self::assertCount(0, $collection);
        self::assertSame([], $collection->get(AssetInterface::class));
        self::assertSame([], \iterator_to_array($collection));
    }
}

/**
 * @internal
 */
readonly class AssetCollectionStubAsset implements AssetInterface
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $hash
     */
    public function __construct(
        public string $id,
        public AssetType $type = AssetType::Style,
        public AssetOrigin $origin = AssetOrigin::Path,
        public string $hash = 'hash',
        public string $value = 'asset.css',
    ) {}
}

/**
 * @internal
 */
readonly class AssetCollectionOtherStubAsset implements AssetInterface
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $hash
     */
    public function __construct(
        public string $id,
        public AssetType $type = AssetType::Script,
        public AssetOrigin $origin = AssetOrigin::Path,
        public string $hash = 'hash',
        public string $value = 'asset.js',
    ) {}
}

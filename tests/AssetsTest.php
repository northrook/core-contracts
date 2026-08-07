<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\AssetCollection;
use Northrook\Contracts\AssetInterface;
use Northrook\Contracts\AssetOrigin;
use Northrook\Contracts\AssetType;
use Northrook\Contracts\ErrorBuffer;
use Northrook\Contracts\RenderStrategy;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Tests\Support\AssetsTestOtherStubAsset;
use Northrook\Contracts\Tests\Support\AssetsTestProviderStub;
use Northrook\Contracts\Tests\Support\AssetsTestStubAsset;
use Northrook\Contracts\Tests\Support\MixedArray;
use PHPUnit\Framework\TestCase;

final class AssetsTest extends TestCase
{
    protected function setUp(): void
    {
        \error_clear_last();
        ErrorBuffer::shared()->reset();
    }

    protected function tearDown(): void
    {
        \error_clear_last();
        ErrorBuffer::shared()->reset();
    }

    public function testKeysAssetsById(): void
    {
        $style  = new AssetsTestStubAsset(id: 'pkg.style');
        $script = new AssetsTestOtherStubAsset(id: 'pkg.script');

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
        $first  = new AssetsTestStubAsset(id: 'pkg.style');
        $second = new AssetsTestStubAsset(
            id   : 'pkg.style',
            value: 'other.css',
        );

        try {
            new AssetCollection($first, $second);
            self::fail('Expected RuntimeException for duplicate asset ID.');
        } catch (RuntimeException $exception) {
            self::assertSame('Duplicate asset ID: pkg.style', $exception->getMessage());

            // Context is deep-frozen; objects become reflective descriptions.
            $assets    = MixedArray::at($exception->context, 'assets');
            $first     = MixedArray::at($assets, 0);
            $second    = MixedArray::at($assets, 1);
            $resolving = MixedArray::at($exception->context, 'resolving');
            $duplicate = MixedArray::at($exception->context, 'duplicate');

            self::assertCount(2, $assets);
            self::assertSame(AssetsTestStubAsset::class, $first['class']);
            self::assertSame(AssetsTestStubAsset::class, $second['class']);
            self::assertSame('asset.css', MixedArray::at($first, 'properties')['value']);
            self::assertSame('other.css', MixedArray::at($second, 'properties')['value']);

            self::assertSame(AssetsTestStubAsset::class, $resolving['class']);
            self::assertSame('other.css', MixedArray::at($resolving, 'properties')['value']);

            self::assertSame(AssetsTestStubAsset::class, $duplicate['class']);
            self::assertSame('asset.css', MixedArray::at($duplicate, 'properties')['value']);
        }
    }

    public function testGetFiltersByClass(): void
    {
        $style = new AssetsTestStubAsset(id: 'pkg.style');
        $other = new AssetsTestOtherStubAsset(id: 'pkg.other');

        $collection = new AssetCollection($style, $other);

        self::assertSame([$style], $collection->get(AssetsTestStubAsset::class));
        self::assertSame([$other], $collection->get(AssetsTestOtherStubAsset::class));
        self::assertSame([$style, $other], $collection->get(AssetInterface::class));
        self::assertSame([], new AssetCollection($other)->get(AssetsTestStubAsset::class));
    }

    public function testCountAndIterationMatchAssets(): void
    {
        $style  = new AssetsTestStubAsset(id: 'pkg.style');
        $script = new AssetsTestOtherStubAsset(id: 'pkg.script');

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
        $collection = new AssetCollection;

        self::assertSame([], $collection->assets);
        self::assertCount(0, $collection);
        self::assertSame([], $collection->get(AssetInterface::class));
        self::assertSame([], \iterator_to_array($collection));
    }

    public function testAssetTypeCases(): void
    {
        self::assertSame('style', AssetType::Style->value);
        self::assertSame('script', AssetType::Script->value);
        self::assertSame('font', AssetType::Font->value);
        self::assertSame('image', AssetType::Image->value);
        self::assertSame('vector', AssetType::Vector->value);
        self::assertSame('svg', AssetType::Svg->value);
        self::assertSame('icon', AssetType::Icon->value);
        self::assertSame('audio', AssetType::Audio->value);
        self::assertSame('video', AssetType::Video->value);
        self::assertSame('binary', AssetType::Binary->value);
        self::assertSame('manifest', AssetType::Manifest->value);
        self::assertSame('worker', AssetType::Worker->value);
        self::assertSame('json', AssetType::JSON->value);
        self::assertSame('xml', AssetType::XML->value);
        self::assertSame('yaml', AssetType::YAML->value);
        self::assertCount(15, AssetType::cases());
    }

    public function testAssetOriginCases(): void
    {
        self::assertSame('path', AssetOrigin::Path->value);
        self::assertSame('url', AssetOrigin::Url->value);
        self::assertSame('data', AssetOrigin::Data->value);
        self::assertSame(AssetOrigin::Url, AssetOrigin::from('url'));
    }

    public function testStubAssetExposesContractProperties(): void
    {
        $asset = new AssetsTestStubAsset(
            id    : 'pkg.style',
            type  : AssetType::Style,
            origin: AssetOrigin::Path,
            hash  : 'abc123',
            value : 'styles/app.css',
        );

        self::assertSame('pkg.style', $asset->id);
        self::assertSame(AssetType::Style, $asset->type);
        self::assertSame(AssetOrigin::Path, $asset->origin);
        self::assertSame('abc123', $asset->hash);
        self::assertSame('styles/app.css', $asset->value);
    }

    public function testProviderInterface(): void
    {
        $style = new AssetsTestStubAsset(id: 'pkg.style');

        $provider = new AssetsTestProviderStub(RenderStrategy::STANDALONE, $style);

        self::assertSame(RenderStrategy::STANDALONE, $provider->renderStrategy);
        self::assertInstanceOf(AssetCollection::class, $provider->getAssets());
        self::assertSame([$style], $provider->getAssets()->get(AssetsTestStubAsset::class));
    }

    public function testRenderStrategyCases(): void
    {
        self::assertSame(
            [
                RenderStrategy::INTEGRATED,
                RenderStrategy::INLINE,
                RenderStrategy::STANDALONE,
                RenderStrategy::AUTO,
            ],
            RenderStrategy::cases(),
        );
    }
}

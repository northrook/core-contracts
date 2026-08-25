<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Reflect;
use Northrook\RuntimeException;
use PHPUnit\Framework\TestCase;

final class ReflectTest extends TestCase
{
    public function testClassThrowsOnMissingClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to reflect class');

        Reflect::class('Northrook\\Contracts\\Tests\\ReflectMissingClass');
    }

    public function testGetPropertiesMapSkipsStaticAndVirtualByDefault(): void
    {
        $map = Reflect::class(new ExportHookFixture('Ada', 'Lovelace'))->getPropertiesMap();

        self::assertArrayHasKey('first', $map);
        self::assertArrayHasKey('last', $map);
        self::assertArrayNotHasKey('full', $map);
        self::assertArrayNotHasKey('ignored', $map);
    }

    public function testGetPropertiesMapOnlyInitialized(): void
    {
        $partial       = new \ReflectionClass(ExportPublicDtoFixture::class)->newInstanceWithoutConstructor();
        $partial->name = 'partial';

        $map = Reflect::class($partial)->getPropertiesMap(onlyInitialized: $partial);

        self::assertArrayHasKey('name', $map);
        self::assertArrayNotHasKey('count', $map);
    }

    public function testGetPropertiesMapChildWinsOnShadowedPrivate(): void
    {
        $map = Reflect::class(ExportShadowChildFixture::class)->getPropertiesMap();

        self::assertArrayHasKey('label', $map);
        self::assertSame(ExportShadowChildFixture::class, $map['label']->getDeclaringClass()->name);
    }

    public function testGetPropertiesMapOnlyPublic(): void
    {
        $map = Reflect::class(ExportRestoreFixture::class)->getPropertiesMap(onlyPublic: true);

        self::assertSame([], $map);
    }
}

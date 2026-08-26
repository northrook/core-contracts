<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use Northrook\Container\Service\Autodiscover;
use Northrook\Container\Service\Tag;

#[Autodiscover(
    binding  : 'unique',
    alias    : ServiceDefinitionAliasInterface::class,
    tag      : 'fixture.tag',
    tags     : [new Tag('fixture.extra', 'arg')],
    arguments: ['mode' => 'attribute'],
    autowire : false,
    preload  : true,
    factory  : 'create',
)]
final class ServiceDefinitionFixture
{
    public static function create(): self
    {
        return new self;
    }

    public function boot(): void {}
}

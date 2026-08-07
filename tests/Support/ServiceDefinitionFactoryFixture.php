<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

final class ServiceDefinitionFactoryFixture
{
    public static function create(): self
    {
        return new self;
    }

    protected static function protectedCreate(): self
    {
        return new self;
    }

    public function instanceCreate(): self
    {
        return new self;
    }
}

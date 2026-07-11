<?php

declare(strict_types=1);

namespace Northrook\Contracts\Interfaces;

interface SingletonInterface
{
    public static function get(): static;
}

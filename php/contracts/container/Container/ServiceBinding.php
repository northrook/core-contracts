<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\Container\Service\Inline;
use Northrook\Container\Service\Scoped;
use Northrook\Container\Service\Shared;
use Northrook\Container\Service\Unique;
use Northrook\InvalidArgumentException;

enum ServiceBinding: string
{
    case Inline = 'inline';
    case Scoped = 'scoped';
    case Shared = 'shared';
    case Unique = 'unique';

    public static function resolve(
        string|BindingAttribute $value,
    ): static {
        $binding = \is_string($value)
            ? $value
            : $value::class;

        return match ($binding) {
            'inline', Inline::class => self::Inline,
            'scoped', Scoped::class => self::Scoped,
            'shared', Shared::class => self::Shared,
            'unique', Unique::class => self::Unique,
            default                 => throw new InvalidArgumentException(
                message: "Invalid service binding: `{$binding}`.",
                context: [
                    'value'   => $value,
                    'binding' => $binding,
                ],
            ),
        };
    }
}

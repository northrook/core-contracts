<?php

declare(strict_types=1);

namespace Northrook\Contracts\Service;

use Northrook\Contracts\Container\AutodiscoverInterface;

/**
 * Add one or more aliases to a `service`, sharing the same binding.
 *
 * Aliases must be fully qualified class names.
 *
 * They are not required to be known to the `container` or resolvable.
 *
 * {@see \Northrook\Contracts\ContainerInterface::get()} can retrieve a `service` by any assigned alias.
 *
 * @implements \IteratorAggregate<int, class-string>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Alias implements \IteratorAggregate, AutodiscoverInterface
{
    /**
     * @var array<int, class-string>
     */
    public array $aliases;

    /**
     * @param class-string ...$alias
     */
    public function __construct(
        string ...$alias,
    ) {
        /**
         * @var array<int, class-string> $set
         */
        $set = \array_keys(\array_fill_keys(
            $alias,
            true,
        ));

        \sort($set);

        $this->aliases = $set;
    }

    /**
     * @return \Traversable<int, class-string>
     * @noinspection PhpDeprecatedSincePhp85Inspection
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->aliases);
    }

    /**
     * @param class-string|array<array-key, class-string>  $alias
     *
     * @return \Northrook\Contracts\Service\Alias
     */
    public static function from(
        string|array $alias,
    ): Alias {
        return new self(...\is_string($alias) ? [$alias] : $alias);
    }

    /**
     * @param \Northrook\Contracts\Service\Alias ...$aliases
     *
     * @return \Northrook\Contracts\Service\Alias
     */
    public static function merge(
        Alias ...$aliases,
    ): Alias {
        $merge = [];
        foreach ($aliases as $alias) {
            $merge = \array_merge($merge, $alias->aliases);
        }
        return new self(...$merge);
    }
}

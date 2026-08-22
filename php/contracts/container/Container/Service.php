<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\Container\Service\Tag;

/**
 * Immutable, finalized service definition for the container COMPILE phase.
 *
 * Typically produced by {@see ServiceDefinition::finalize()}.
 *
 * Primary constructor/factory overrides remain on the reserved
 * {@see \Northrook\ContainerInterface::DEFAULT_REFERENCE} tag when present;
 * there is no separate arguments field.
 */
final readonly class Service
{
    /**
     * @param non-empty-lowercase-string           $id
     * @param class-string                         $class
     * @param \Northrook\Container\ServiceBinding  $binding
     * @param array<int, class-string>             $aliases
     * @param \Northrook\Container\Service\Tag[]   $tags
     * @param bool                                 $autowire
     * @param bool                                 $preload
     * @param false|string                         $factory
     * @param \Northrook\Callback[]                $callbacks
     */
    public function __construct(
        public string         $id,
        public string         $class,
        public ServiceBinding $binding,
        public array          $aliases,
        public array          $tags,
        public bool           $autowire,
        public bool           $preload,
        public false|string   $factory,
        public array          $callbacks,
    ) {}

    public function hasTag(
        string $tag,
    ): bool {
        return \array_any(
            array   : $this->tags,
            callback: static fn(Tag $existing) => $existing->reference === $tag,
        );
    }

    public function hasAlias(
        string $alias,
    ): bool {
        return \in_array($alias, $this->aliases, true);
    }
}

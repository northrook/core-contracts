<?php

declare(strict_types=1);

namespace Northrook\Container\Service;

use Northrook\Callback;
use Northrook\Container\AutodiscoverInterface;
use Northrook\Container\ServiceBinding;

/**
 * This attribute is used to configure a `service` for autodiscovery.
 *
 * Should not handle any logic or execution viability, only configuration and type assertions.
 *
 * Non-empty {@see $arguments} materialize as a reserved {@see Tag} keyed by
 * {@see \Northrook\ContainerInterface::DEFAULT_REFERENCE} when registered via
 * {@see \Northrook\Container\ServiceDefinition::register()}.
 *
 * @phpstan-type ArgumentMap array<non-empty-string|int<0, max>, mixed>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
readonly class Autodiscover implements AutodiscoverInterface
{
    public null|ServiceBinding $binding;

    public null|Alias $aliases;

    /**
     * @var \Northrook\Container\Service\Tag[]
     */
    public null|array $tags;

    /**
     * Primary constructor/factory argument overrides.
     *
     * Materialized as {@see \Northrook\ContainerInterface::DEFAULT_REFERENCE} on register.
     *
     * @var null|ArgumentMap
     */
    public null|array $arguments;

    /**
     * @var bool `true` always preloads the service, `false` loads lazily on first use.
     */
    public null|bool $preload;

    /**
     * Whether to autowire the service.
     *
     * When `false`, implicit type-hint resolution is disabled; explicit
     * {@see $arguments} and {@see \Northrook\Container\Autowire} remain.
     *
     * @var null|bool
     */
    public null|bool $autowire;

    /**
     * `factory` string must be a public static method on the class.
     *
     * @var null|string
     */
    public null|string $factory;

    /**
     * Callbacks the container invokes on the service immediately after instantiation.
     *
     * Typically public methods on the service; any {@see \Northrook\Callback} is permitted.
     *
     * @var null|\Northrook\Callback[]
     */
    public null|array $callbacks;

    /**
     * @param null|\Northrook\Container\ServiceBinding|string  $binding
     * @param null|class-string|class-string[]                 $alias
     * @param null|non-empty-string                            $tag
     * @param null|list<non-empty-string|Tag>                  $tags
     * @param null|ArgumentMap                                 $arguments
     * @param null|bool                                        $autowire
     * @param null|bool                                        $preload
     * @param null|string                                      $factory
     * @param null|Callback|array<Callback>                    $callbacks
     */
    public function __construct(
        null|ServiceBinding|string $binding = null,
        null|string|array          $alias = null,
        null|string                $tag = null,
        null|array                 $tags = null,
        null|array                 $arguments = null,
        null|bool                  $autowire = null,
        null|bool                  $preload = null,
        null|string                $factory = null,
        null|Callback|array        $callbacks = null,
    ) {
        $this->binding   = \is_string($binding) ? ServiceBinding::resolve($binding) : $binding;
        $this->aliases   = empty($alias) ? null : Alias::from($alias);
        $this->tags      = $this->resolveTags($tag, $tags);
        $this->arguments = $arguments === [] ? null : $arguments;
        $this->autowire  = $autowire;
        $this->preload   = $preload;
        $this->factory   = $factory;
        $this->callbacks = $this->resolveCallbacks($callbacks);
    }

    /**
     * @param null|Callback|array<Callback> $callbacks
     *
     * @return null|list<Callback>
     */
    private function resolveCallbacks(
        null|Callback|array $callbacks = null,
    ): null|array {
        if ($callbacks === null || $callbacks === []) {
            return null;
        }

        return \is_array($callbacks) ? \array_values($callbacks) : [$callbacks];
    }

    /**
     * @param null|non-empty-string           $tag
     * @param null|list<non-empty-string|Tag> $tags
     *
     * @return null|list<Tag>
     */
    private function resolveTags(
        null|string $tag,
        null|array  $tags,
    ): null|array {
        if ($tag === null && $tags === null) {
            return null;
        }

        $resolved = [];

        if (! empty($tag)) {
            $resolved[] = new Tag($tag);
        }

        if (! empty($tags)) {
            foreach ($tags as $entry) {
                $resolved[] = $entry instanceof Tag ? $entry : new Tag($entry);
            }
        }

        return $resolved ?: null;
    }
}

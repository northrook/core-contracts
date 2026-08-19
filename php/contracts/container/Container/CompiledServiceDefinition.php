<?php

declare(strict_types=1);

namespace Northrook\Container;

use Northrook\DataObject;
use Northrook\Snapshot;

/**
 * Immutable, finalized service definition for the container COMPILE phase.
 *
 * Only constructible via {@see ServiceDefinition::finalize()}.
 *
 * Primary constructor/factory overrides remain on the reserved
 * {@see \Northrook\ContainerInterface::DEFAULT_REFERENCE} tag when present;
 * there is no separate arguments field.
 *
 * @phpstan-type ExportArray array{
 *     id: non-empty-lowercase-string,
 *     class: class-string,
 *     binding: string,
 *     aliases: array<int, class-string>,
 *     tags: list<array{reference: non-empty-string, arguments: null|array<array-key, mixed>}>,
 *     autowire: bool,
 *     preload: bool,
 *     factory: false|string,
 *     callbacks: list<array{descriptor: mixed, args: list<mixed>}>,
 *     locked: bool
 * }
 */
final readonly class CompiledServiceDefinition extends DataObject
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
     * @param \Northrook\Callback[]      $callbacks
     * @param bool                                 $locked
     */
    private function __construct(
        public string         $id,
        public string         $class,
        public ServiceBinding $binding,
        public array          $aliases,
        public array          $tags,
        public bool           $autowire,
        public bool           $preload,
        public false|string   $factory,
        public array          $callbacks,
        public bool           $locked,
    ) {
        parent::__construct();
    }

    public static function from(
        ServiceDefinition $definition,
    ): self {
        return new self(
            id       : $definition->id,
            class    : $definition->class,
            binding  : $definition->binding,
            aliases  : $definition->aliases,
            tags     : $definition->tags,
            autowire : $definition->autowire,
            preload  : $definition->preload,
            factory  : $definition->factory,
            callbacks: $definition->callbacks,
            locked   : $definition->locked,
        );
    }

    /**
     * Same stable shape as {@see ServiceDefinition::export()}.
     *
     * @return ExportArray
     */
    public function toArray(): array
    {
        $tags = [];
        foreach ($this->tags as $tag) {
            $tags[] = [
                'reference' => $tag->reference,
                'arguments' => self::exportTagArguments($tag->arguments),
            ];
        }

        $callbacks = [];
        foreach ($this->callbacks as $callback) {
            $callbacks[] = $callback->__serialize();
        }

        return [
            'id'        => $this->id,
            'class'     => $this->class,
            'binding'   => $this->binding->value,
            'aliases'   => $this->aliases,
            'tags'      => $tags,
            'autowire'  => $this->autowire,
            'preload'   => $this->preload,
            'factory'   => $this->factory,
            'callbacks' => $callbacks,
            'locked'    => $this->locked,
        ];
    }

    /**
     * @param null|array<array-key, mixed> $arguments
     *
     * @return null|array<array-key, mixed>
     */
    private static function exportTagArguments(
        null|array $arguments,
    ): null|array {
        if ($arguments === null) {
            return null;
        }

        $frozen = Snapshot::freeze($arguments);

        return \is_array($frozen) ? $frozen : [$frozen];
    }
}

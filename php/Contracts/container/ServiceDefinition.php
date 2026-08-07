<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts\Container\AutodiscoverInterface;
use Northrook\Contracts\Container\BindingAttribute;
use Northrook\Contracts\Container\ServiceBinding;
use Northrook\Contracts\Service\Alias;
use Northrook\Contracts\Service\Autodiscover;
use Northrook\Contracts\Service\Tag;
use Northrook\Contracts\ServiceDefinition\CompiledServiceDefinition;

/**
 * Define a service, commonly through the {@see AutodiscoverInterface}.
 *
 * Binding and other fields may be mutated by compiler passes until {@see $locked}
 * is set. When locked, no field mutation is allowed except {@see lock()} itself
 * (`lock(false)` is for tests / rare compiler recovery only).
 *
 * Primary constructor/factory overrides live on a reserved {@see Tag} keyed by
 * {@see ContainerInterface::DEFAULT_REFERENCE}. Prefer {@see setArguments()} /
 * {@see setArgument()} over raw {@see addTag()} for that slot.
 *
 * When {@see $autowire} is `false`, implicit type-hint resolution is disabled;
 * explicit {@see DEFAULT_REFERENCE} arguments and {@see Autowire} attributes remain.
 *
 * Use {@see export()} for dumps and {@see finalize()} for the compile pipeline.
 *
 * @phpstan-import-type TagFrom from \Northrook\Contracts\Service\Tag
 * @phpstan-type ArgumentKey non-empty-string|int<0, max>
 * @phpstan-type ArgumentMap array<non-empty-string|int<0, max>, mixed>
 */
final class ServiceDefinition
{
    /**
     * @var \ReflectionClass<object>
     */
    private readonly \ReflectionClass $reflection;

    /**
     * @var non-empty-lowercase-string
     */
    public readonly string $id;

    /**
     * @var class-string
     */
    public readonly string $class;

    private(set) bool $locked;

    /**
     * @var array<int, class-string>
     */
    private(set) array $aliases;

    /**
     * @var list<Tag>
     */
    private(set) array $tags;

    private(set) ServiceBinding $binding;

    /**
     * When `false`, the container skips implicit type-hint resolution.
     *
     * Explicit {@see ContainerInterface::DEFAULT_REFERENCE} arguments and
     * {@see Autowire} attributes are still honored.
     */
    private(set) bool $autowire;

    /**
     * @var bool `true` always preloads the service, `false` loads lazily on first use.
     */
    private(set) bool $preload;

    /**
     * Callbacks the container invokes on the service immediately after instantiation.
     *
     * Typically public methods on the service; any {@see Callback} is permitted.
     *
     * @var \Northrook\Contracts\Callback[]
     */
    private(set) array $callbacks;

    /**
     * If set, the service will be initialized from a static method.
     *
     * Must be a `public static` method of the {@see ServiceDefinition::$class}.
     *
     * @var false|string
     */
    private(set) false|string $factory;

    /**
     * @param class-string                                                        $class
     * @param ServiceBinding                                                      $binding
     * @param class-string|Alias|array<array-key, class-string>                   $aliases
     * @param array<int, Tag|TagFrom>                                             $tags
     * @param ArgumentMap                                                         $arguments
     * @param bool                                                                $autowire
     * @param bool                                                                $preload
     * @param false|string                                                        $factory
     * @param \Northrook\Contracts\Callback[]                                     $callbacks
     * @param bool                                                                $locked
     */
    private function __construct(
        string             $class,
        ServiceBinding     $binding,
        string|Alias|array $aliases,
        array              $tags,
        array              $arguments,
        bool               $autowire,
        bool               $preload,
        false|string       $factory,
        array              $callbacks,
        bool               $locked,
    ) {
        if (! \class_exists($class)) {
            throw new ContainerException(
                message: "Service class `{$class}` does not exist.",
                context: \get_defined_vars(),
            );
        }

        $this->class      = $class;
        $this->id         = $this->resolveId();
        $this->binding    = $binding;
        $this->reflection = new \ReflectionClass($class);
        $this->locked     = false;

        $this->setFactory($factory);
        $this->setAliases($aliases);
        $this->setTags($tags);
        $this->setCallbacks(...$callbacks);
        $this->setArguments($arguments);

        $this->autowire = $autowire;
        $this->preload  = $preload;
        $this->locked   = $locked;
    }

    /**
     * Set binding during a mutable compiler pass.
     *
     * @throws ContainerException when {@see $locked}
     */
    public function binding(
        string|ServiceBinding|BindingAttribute $binding,
        bool                                   $lock = false,
    ): self {
        $this->frozen();

        $this->binding = $binding instanceof ServiceBinding
            ? $binding
            : ServiceBinding::resolve($binding);

        if ($lock) {
            $this->locked = true;
        }

        return $this;
    }

    public function autowire(
        bool $set = true,
    ): self {
        $this->frozen();
        $this->autowire = $set;

        return $this;
    }

    public function preload(
        bool $set = true,
    ): self {
        $this->frozen();
        $this->preload = $set;

        return $this;
    }

    /**
     * @param class-string|Alias|array<array-key, class-string> $aliases
     */
    public function setAliases(
        string|Alias|array $aliases,
    ): self {
        $this->frozen();
        $this->aliases = $this->normalizeAliases($aliases);

        return $this;
    }

    /**
     * @param class-string|Alias ...$alias
     *
     * @throws ContainerException on duplicate alias
     */
    public function addAlias(
        string|Alias ...$alias,
    ): self {
        $this->frozen();

        $merged = $this->aliases;

        foreach ($alias as $entry) {
            foreach ($this->normalizeAliases($entry) as $class) {
                if (\in_array($class, $merged, true)) {
                    throw new ContainerException(
                        message: "Alias `{$class}` is already registered on service `{$this->class}`.",
                        context: [
                            'class'   => $this->class,
                            'alias'   => $class,
                            'aliases' => $merged,
                        ],
                    );
                }
                $merged[] = $class;
            }
        }

        $this->aliases = Alias::from($merged)->aliases;

        return $this;
    }

    /**
     * @param class-string $alias
     */
    public function hasAlias(
        string $alias,
    ): bool {
        return \in_array($alias, $this->aliases, true);
    }

    /**
     * @param class-string ...$alias
     */
    public function removeAlias(
        string ...$alias,
    ): self {
        $this->frozen();

        $remove        = \array_fill_keys($alias, true);
        $this->aliases = \array_values(
            \array_filter(
                $this->aliases,
                static fn(string $existing): bool => ! isset($remove[$existing]),
            ),
        );

        return $this;
    }

    /**
     * @param array<int, Tag|TagFrom> $tags
     */
    public function setTags(
        array $tags,
    ): self {
        $this->frozen();
        $this->tags = $this->normalizeTags($tags);

        return $this;
    }

    /**
     * @param TagFrom $tag
     *
     * @throws ContainerException on duplicate tag reference
     */
    public function addTag(
        string|array $tag,
        mixed ...    $arguments,
    ): self {
        $this->frozen();

        $resolved = Tag::from($tag, ...$arguments);

        if ($this->hasTag($resolved->reference)) {
            throw new ContainerException(
                message: "Tag `{$resolved->reference}` is already registered on service `{$this->class}`.",
                context: [
                    'class'     => $this->class,
                    'reference' => $resolved->reference,
                    'tags'      => $this->tags,
                ],
            );
        }

        $this->tags[] = $resolved;

        return $this;
    }

    public function hasTag(
        string $reference,
    ): bool {
        return $this->findTagIndex($reference) !== null;
    }

    public function getTag(
        string $reference,
    ): null|Tag {
        $index = $this->findTagIndex($reference);

        return $index === null ? null : $this->tags[$index];
    }

    public function removeTag(
        string ...$reference,
    ): self {
        $this->frozen();

        $remove     = \array_fill_keys($reference, true);
        $this->tags = \array_values(
            \array_filter(
                $this->tags,
                static fn(Tag $tag): bool => ! isset($remove[$tag->reference]),
            ),
        );

        return $this;
    }

    /**
     * Replace primary constructor/factory arguments on the reserved
     * {@see ContainerInterface::DEFAULT_REFERENCE} tag.
     *
     * Empty `$arguments` removes the reserved tag.
     *
     * @param ArgumentMap $arguments
     *
     * @throws ContainerException when {@see $locked}
     * @throws InvalidArgumentException when a key is invalid
     */
    public function setArguments(
        array $arguments,
    ): self {
        $this->frozen();

        $normalized = $this->normalizeArgumentMap($arguments);

        if ($normalized === []) {
            return $this->clearArguments();
        }

        return $this->replaceDefaultReferenceTag($normalized);
    }

    /**
     * @param ArgumentKey $name
     *
     * @throws ContainerException when {@see $locked} or `$name` already exists
     * @throws InvalidArgumentException when `$name` is invalid
     */
    public function addArgument(
        string|int $name,
        mixed      $value,
    ): self {
        $this->frozen();
        $this->assertArgumentKey($name);

        $current = $this->defaultReferenceArguments();

        if (\array_key_exists($name, $current)) {
            throw new ContainerException(
                message: "Argument `{$name}` is already registered on service `{$this->class}`.",
                context: [
                    'class'     => $this->class,
                    'argument'  => $name,
                    'arguments' => $current,
                ],
            );
        }

        $current[$name] = $value;

        return $this->replaceDefaultReferenceTag($current);
    }

    /**
     * @param ArgumentKey $name
     *
     * @throws ContainerException when {@see $locked}
     * @throws InvalidArgumentException when `$name` is invalid
     */
    public function setArgument(
        string|int $name,
        mixed      $value,
    ): self {
        $this->frozen();
        $this->assertArgumentKey($name);

        $current        = $this->defaultReferenceArguments();
        $current[$name] = $value;

        return $this->replaceDefaultReferenceTag($current);
    }

    /**
     * @param ArgumentKey $name
     */
    public function hasArgument(
        string|int $name,
    ): bool {
        return \array_key_exists($name, $this->defaultReferenceArguments());
    }

    /**
     * @param ArgumentKey $name
     */
    public function getArgument(
        string|int $name,
    ): mixed {
        $arguments = $this->defaultReferenceArguments();

        return $arguments[$name] ?? null;
    }

    /**
     * @param ArgumentKey ...$name
     *
     * @throws ContainerException when {@see $locked}
     */
    public function removeArgument(
        string|int ...$name,
    ): self {
        $this->frozen();

        $current = $this->defaultReferenceArguments();

        if ($current === []) {
            return $this;
        }

        foreach ($name as $key) {
            unset($current[$key]);
        }

        if ($current === []) {
            return $this->clearArguments();
        }

        return $this->replaceDefaultReferenceTag($current);
    }

    /**
     * Remove the reserved {@see ContainerInterface::DEFAULT_REFERENCE} tag.
     *
     * @throws ContainerException when {@see $locked}
     */
    public function clearArguments(): self
    {
        $this->frozen();

        if (! $this->hasTag(ContainerInterface::DEFAULT_REFERENCE)) {
            return $this;
        }

        return $this->removeTag(ContainerInterface::DEFAULT_REFERENCE);
    }

    public function setCallbacks(
        Callback ...$callbacks,
    ): self {
        $this->frozen();

        $this->callbacks = \array_values($callbacks);

        return $this;
    }

    public function addCallback(
        Callback $callback,
    ): self {
        $this->frozen();
        $this->callbacks[] = $callback;

        return $this;
    }

    public function hasCallback(
        Callback $callback,
    ): bool {
        return \array_any(
            array   : $this->callbacks,
            callback: static fn($existing) => (
                $existing === $callback
                || $existing->__serialize() === $callback->__serialize()
            ),
        );
    }

    public function getCallback(
        int $index,
    ): null|Callback {
        return $this->callbacks[$index] ?? null;
    }

    /**
     * @throws ContainerException when `$index` is out of range
     */
    public function removeCallback(
        int $index,
    ): self {
        $this->frozen();

        if (! \array_key_exists($index, $this->callbacks)) {
            throw new ContainerException(
                message: "Callback index `{$index}` does not exist on service `{$this->class}`.",
                context: [
                    'class'     => $this->class,
                    'index'     => $index,
                    'callbacks' => $this->callbacks,
                ],
            );
        }

        unset($this->callbacks[$index]);
        $this->callbacks = \array_values($this->callbacks);

        return $this;
    }

    /**
     * @param false|string|array{0: class-string, 1: string} $factory
     */
    public function setFactory(
        false|string|array $factory,
    ): self {
        $this->frozen();

        if ($factory === false) {
            $this->factory = false;

            return $this;
        }

        if (\is_string($factory)) {
            if (\str_contains($factory, '::')) {
                [$factoryClass, $method] = \explode('::', $factory, 2);

                if ($factoryClass !== $this->class) {
                    throw new ContainerException(
                        message: "Service factory class `{$factoryClass}` does not match service class `{$this->class}`.",
                    );
                }
            } else {
                $method = $factory;
            }
        } else {
            if ($factory[0] !== $this->class) {
                throw new ContainerException(
                    message: "Service factory class `{$factory[0]}` does not match service class `{$this->class}`.",
                );
            }
            $method = $factory[1];
        }

        if (! $this->reflection->hasMethod($method)) {
            throw new ContainerException(
                message: "Service factory `{$method}` does not exist.",
                context: \get_defined_vars(),
            );
        }

        try {
            $reflectionMethod = $this->reflection->getMethod($method);

            if (! $reflectionMethod->isPublic()) {
                throw new ContainerException(
                    message: "Service factory `{$method}` is not public.",
                );
            }

            if (! $reflectionMethod->isStatic()) {
                throw new ContainerException(
                    message: "Service factory `{$method}` is not static.",
                );
            }
        } catch (\ReflectionException $exception) {
            throw new ContainerException(
                message : "Service factory `{$method}` is not a valid factory method.",
                previous: $exception,
            );
        }

        $this->factory = $method;

        return $this;
    }

    public function clearFactory(): self
    {
        return $this->setFactory(false);
    }

    /**
     * @param class-string                         $class
     * @param null|Autodiscover                    $autodiscover
     * @param array<array-key, class-string>       $aliases
     * @param array<int, Tag|TagFrom>              $tags
     * @param ArgumentMap                          $arguments
     * @param bool                                 $autowire
     * @param bool                                 $preload
     * @param false|string                         $factory
     * @param \Northrook\Contracts\Callback[]      $callbacks
     * @param ServiceBinding                       $binding
     * @param bool                                 $locked
     */
    public static function register(
        string            $class,
        null|Autodiscover $autodiscover = null,
        array             $aliases = [],
        array             $tags = [],
        array             $arguments = [],
        bool              $autowire = true,
        bool              $preload = false,
        false|string      $factory = false,
        array             $callbacks = [],
        ServiceBinding    $binding = ServiceBinding::Shared,
        bool              $locked = false,
    ): ServiceDefinition {
        return new self(
            $class,
            binding: $autodiscover->binding ?? $binding,
            aliases: $autodiscover->aliases->aliases ?? $aliases,
            tags: $autodiscover->tags ?? $tags,
            arguments: $autodiscover->arguments ?? $arguments,
            autowire: $autodiscover->autowire ?? $autowire,
            preload: $autodiscover->preload ?? $preload,
            factory: $autodiscover->factory ?? $factory,
            callbacks: $autodiscover->callbacks ?? $callbacks,
            locked: $locked,
        );
    }

    /**
     * Plain JSON-friendly snapshot of the current definition state.
     *
     * @return array{
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
    public function export(): array
    {
        return $this->toExportArray();
    }

    /**
     * Lock the definition and produce an immutable compile-time DTO.
     *
     * Intended for {@see CompilerInterface} COMPILE phase consumers.
     */
    public function finalize(): CompiledServiceDefinition
    {
        if (! $this->locked) {
            $this->lock();
        }

        return CompiledServiceDefinition::from($this);
    }

    /**
     * Prevent further definition mutation.
     *
     * {@see lock(false)} bypasses {@see frozen()} and is for tests / rare recovery only.
     */
    public function lock(
        bool $set = true,
    ): self {
        $this->locked = $set;

        return $this;
    }

    /**
     * Resolve the service ID (lowercase dotted FQCN).
     *
     * Uniqueness is the implementing compiler's responsibility.
     *
     * @return non-empty-lowercase-string
     */
    private function resolveId(): string
    {
        return \strtolower(\str_replace('\\', '.', $this->class));
    }

    /**
     * @param class-string|Alias|array<array-key, class-string> $aliases
     *
     * @return array<int, class-string>
     */
    private function normalizeAliases(
        string|Alias|array $aliases,
    ): array {
        if ($aliases instanceof Alias) {
            return $aliases->aliases;
        }

        if ($aliases === [] || $aliases === '') {
            return [];
        }

        return Alias::from($aliases)->aliases;
    }

    /**
     * @param array<int, Tag|TagFrom> $tags
     *
     * @return list<Tag>
     */
    private function normalizeTags(
        array $tags,
    ): array {
        $resolved = [];
        $seen     = [];

        foreach ($tags as $tag) {
            $item = $tag instanceof Tag ? $tag : Tag::from($tag);

            if (isset($seen[$item->reference])) {
                throw new ContainerException(
                    message: "Tag `{$item->reference}` is duplicated on service `{$this->class}`.",
                    context: [
                        'class'     => $this->class,
                        'reference' => $item->reference,
                    ],
                );
            }

            $seen[$item->reference] = true;
            $resolved[]             = $item;
        }

        return $resolved;
    }

    private function findTagIndex(
        string $reference,
    ): null|int {
        return \array_find_key(
            array   : $this->tags,
            callback: fn($tag) => $tag->reference === $reference,
        );
    }

    /**
     * @return ArgumentMap
     */
    private function defaultReferenceArguments(): array
    {
        $arguments = $this->getTag(ContainerInterface::DEFAULT_REFERENCE)?->arguments;

        return $arguments ?? [];
    }

    /**
     * @param ArgumentMap $arguments
     */
    private function replaceDefaultReferenceTag(
        array $arguments,
    ): self {
        $this->removeTag(ContainerInterface::DEFAULT_REFERENCE);
        $this->tags[] = new Tag(
            ContainerInterface::DEFAULT_REFERENCE,
            ...$this->argumentsForVariadic($arguments),
        );

        return $this;
    }

    /**
     * Unpack order must put positional keys before named keys.
     *
     * @param ArgumentMap $arguments
     *
     * @return ArgumentMap
     */
    private function argumentsForVariadic(
        array $arguments,
    ): array {
        $positional = [];
        $named      = [];

        foreach ($arguments as $key => $value) {
            if (\is_int($key)) {
                $positional[$key] = $value;
            } else {
                $named[$key] = $value;
            }
        }

        \ksort($positional);

        return $positional + $named;
    }

    /**
     * @param array<array-key, mixed> $arguments
     *
     * @return ArgumentMap
     *
     * @throws InvalidArgumentException
     */
    private function normalizeArgumentMap(
        array $arguments,
    ): array {
        $normalized = [];

        foreach ($arguments as $name => $value) {
            if (! \is_string($name) && ! \is_int($name)) {
                throw new InvalidArgumentException(
                    message : 'Service argument keys must be non-empty strings or non-negative integers.',
                    name    : 'arguments',
                    expected: 'non-empty-string|int<0, max>',
                    received: $name,
                );
            }

            $this->assertArgumentKey($name);
            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /**
     * @param string|int $name
     *
     * @throws InvalidArgumentException
     *
     * @phpstan-assert ArgumentKey $name
     */
    private function assertArgumentKey(
        string|int $name,
    ): void {
        if (\is_int($name)) {
            if ($name < 0) {
                throw new InvalidArgumentException(
                    message : 'Positional service argument keys must be non-negative integers.',
                    name    : 'name',
                    expected: 'int<0, max>',
                    received: $name,
                );
            }

            return;
        }

        if ($name === '' || \str_starts_with($name, '$')) {
            throw new InvalidArgumentException(
                message : 'Named service argument keys must be non-empty parameter names without a leading `$`.',
                name    : 'name',
                expected: 'non-empty-string without leading $',
                received: $name,
            );
        }
    }

    /**
     * @return array{
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
    private function toExportArray(): array
    {
        $tags = [];
        foreach ($this->tags as $tag) {
            $tags[] = [
                'reference' => $tag->reference,
                'arguments' => $this->exportTagArguments($tag->arguments),
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
    private function exportTagArguments(
        null|array $arguments,
    ): null|array {
        if ($arguments === null) {
            return null;
        }

        $frozen = Snapshot::freeze($arguments);

        return \is_array($frozen) ? $frozen : [$frozen];
    }

    /**
     * @param null|array<string, mixed> $context
     *
     * @throws ContainerException when {@see $locked}
     */
    private function frozen(
        null|string $message = null,
        null|array  $context = null,
    ): void {
        if ($this->locked) {
            throw new ContainerException(
                message: $message ?? "Service `{$this->class}` is locked and cannot be overridden.",
                context: $context ?? ['class' => $this->class],
            );
        }
    }
}

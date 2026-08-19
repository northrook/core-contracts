<?php /** @noinspection PhpLoopCanBeConvertedToArrayAnyInspection */

declare(strict_types=1);

namespace Northrook;

// TODO : likely lives in Debug

use JsonSerializable;
use Northrook\Context\MemoryManager;
use Northrook\Runtime\ReservedMemory;
use Stringable;
use UnitEnum;

/**
 * Immutable snapshot of a context value for logging and exception payloads.
 *
 * Records the source {@see gettype()} and, where possible, a detached copy of the value.
 * Values that cannot be copied are replaced with a descriptive string.
 *
 * Objects are walked reflectively (no `serialize` / `clone`) so dumps never rehydrate
 * service graphs or run magic methods. {@see Parameter} and other DTOs keep their
 * envelope; `$value` is redacted via {@see Redaction} when `$secret` / `#[Secret]` /
 * `#[\SensitiveParameter]` apply (honours {@see \Northrook\Context::$secretRedactor};
 * attribute `$conditions` become tag context).
 *
 * Array reference cycles are broken by comparing each nested array against an ancestor
 * stack with {@see ===}. Object cycles use {@see \WeakMap} and a `[Recursion: …]` marker.
 *
 * Nesting is bounded by a generous depth + node budget derived from remaining
 * `memory_limit` headroom (overrideable). A small {@see ReservedMemory} cushion is
 * held for the duration so a fatal OOM can still free a slab in shutdown.
 *
 * @phpstan-type PhpType 'array'|'boolean'|'double'|'integer'|'NULL'|'object'|'resource'|'string'
 */
final class Snapshot implements JsonSerializable, Stringable
{
    private const string ARRAY_RECURSION = '[Recursion]';

    private const string TRUNCATED_DEPTH = '[Snapshot: max depth]';

    private const string TRUNCATED_NODES = '[Snapshot: budget exhausted]';

    /** @var int<1, max> Soft floor / ceiling for auto depth. */
    private const int MIN_DEPTH = 16;

    private const int MAX_DEPTH = 64;

    /** @var int<1, max> Soft floor / ceiling for auto node count. */
    private const int MIN_NODES = 1_024;

    private const int MAX_NODES = 65_536;

    /** Fraction of remaining headroom Snapshot may aim to use. */
    private const float HEADROOM_FRACTION = 0.35;

    /** Rough average bytes attributed per snapshotted node when sizing the budget. */
    private const int BYTES_PER_NODE = 512;

    /** OOM cushion held while snapshotting (bytes). */
    private const int CUSHION_BYTES = 262_144;

    private static null|ReservedMemory $cushion = null;

    private static int $cushionDepth = 0;

    private static bool $shutdownRegistered = false;

    /**
     * @param PhpType  $type
     * @param mixed    $value
     */
    public function __construct(
        public readonly string $type,
        public readonly mixed  $value,
    ) {}

    /**
     * @param null|int<1, max> $maxDepth Explicit depth cap; `null` = headroom-biased
     * @param null|int<1, max> $maxNodes Explicit node cap; `null` = headroom-biased
     */
    public static function value(
        mixed    $value,
        null|int $maxDepth = null,
        null|int $maxNodes = null,
    ): mixed {
        return self::withBudget(
            $maxDepth,
            $maxNodes,
            static fn(SnapshotBudget $budget): mixed => self::snapshotValue(
                $value,
                budget: $budget,
            ),
        );
    }

    /**
     * @param null|int<1, max> $maxDepth Explicit depth cap; `null` = headroom-biased
     * @param null|int<1, max> $maxNodes Explicit node cap; `null` = headroom-biased
     */
    public static function from(
        mixed    $value,
        null|int $maxDepth = null,
        null|int $maxNodes = null,
    ): self {
        return new self(
            self::phpType($value),
            self::value($value, $maxDepth, $maxNodes),
        );
    }

    /**
     * @param list<mixed>      $values
     * @param null|int<1, max> $maxDepth
     * @param null|int<1, max> $maxNodes
     *
     * @return list<self>
     */
    public static function parse(
        array    $values,
        null|int $maxDepth = null,
        null|int $maxNodes = null,
    ): array {
        $snapshots = [];

        foreach ($values as $value) {
            $snapshots[] = self::from($value, $maxDepth, $maxNodes);
        }

        return $snapshots;
    }

    public function __toString(): string
    {
        if (\is_string($this->value)) {
            return $this->value;
        }

        $encoded = \json_encode(
            $this,
            \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE,
        );

        return \is_string($encoded)
            ? $encoded
            : '[Unserializable Snapshot]';
    }

    /**
     * @return array{type: PhpType, value: mixed}
     */
    public function jsonSerialize(): array
    {
        return [
            'type'  => $this->type,
            'value' => $this->value,
        ];
    }

    /**
     * @param null|int<1, max> $maxDepth
     * @param null|int<1, max> $maxNodes
     */
    public static function freeze(
        mixed    $value,
        null|int $maxDepth = null,
        null|int $maxNodes = null,
    ): mixed {
        return self::from($value, $maxDepth, $maxNodes)->value;
    }

    /**
     * @param null|array<array-key, mixed> $context
     * @param null|int<1, max>             $maxDepth
     * @param null|int<1, max>             $maxNodes
     *
     * @return array<array-key, mixed>
     */
    public static function context(
        null|array $context,
        null|int   $maxDepth = null,
        null|int   $maxNodes = null,
    ): array {
        if ($context === null || $context === []) {
            return [];
        }

        return self::withBudget(
            $maxDepth,
            $maxNodes,
            fn: static function(
                SnapshotBudget $budget,
            ) use ($context): array {
                return \array_map(
                    callback: static fn($value) => self::snapshotValue(
                        value : $value,
                        budget: $budget,
                    ),
                    array   : $context,
                );
            },
        );
    }

    /**
     * @template T
     *
     * @param null|int<1, max>              $maxDepth
     * @param null|int<1, max>              $maxNodes
     * @param callable(SnapshotBudget): T   $fn
     *
     * @return T
     */
    private static function withBudget(
        null|int $maxDepth,
        null|int $maxNodes,
        callable $fn,
    ): mixed {
        self::armCushion();

        try {
            return $fn(self::resolveBudget($maxDepth, $maxNodes));
        } finally {
            self::releaseCushion();
        }
    }

    /**
     * Hold a disposable slab so an OOM during snapshotting can free a little in shutdown.
     */
    private static function armCushion(): void
    {
        self::$cushion ??= new ReservedMemory(self::CUSHION_BYTES);

        if (! self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            \register_shutdown_function(static function(): void {
                self::$cushion?->release();
                self::$cushionDepth = 0;
            });
        }

        if (self::$cushionDepth === 0) {
            self::$cushion->reserve();
        }

        self::$cushionDepth++;
    }

    private static function releaseCushion(): void
    {
        if (self::$cushionDepth === 0) {
            return;
        }

        self::$cushionDepth--;

        if (self::$cushionDepth === 0) {
            self::$cushion?->release();
        }
    }

    /**
     * @param null|int<1, max> $maxDepth
     * @param null|int<1, max> $maxNodes
     */
    private static function resolveBudget(
        null|int $maxDepth,
        null|int $maxNodes,
    ): SnapshotBudget {
        if ($maxDepth === null || $maxNodes === null) {
            $remaining = MemoryManager::getMemoryRemaining();

            if ($remaining === true) {
                // Unlimited `memory_limit` — still bound the walk, generously.
                $autoDepth = self::MAX_DEPTH;
                $autoNodes = self::MAX_NODES;
            } else {
                $spendable = (int) \max(
                    0,
                    ( $remaining - self::CUSHION_BYTES ) * self::HEADROOM_FRACTION,
                );

                $autoNodes = self::clamp(
                    \intdiv($spendable, self::BYTES_PER_NODE),
                    self::MIN_NODES,
                    self::MAX_NODES,
                );

                // ~256 KiB of spendable headroom per depth step above the floor.
                $autoDepth = self::clamp(
                    self::MIN_DEPTH + \intdiv($spendable, 256 * 1024),
                    self::MIN_DEPTH,
                    self::MAX_DEPTH,
                );
            }

            $maxDepth ??= $autoDepth;
            $maxNodes ??= $autoNodes;
        }

        return new SnapshotBudget($maxDepth, $maxNodes);
    }

    /**
     * @param int         $value
     * @param int<1, max> $min
     * @param int<1, max> $max
     *
     * @return int<1, max>
     */
    private static function clamp(
        int $value,
        int $min,
        int $max,
    ): int {
        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;
    }

    /**
     * @param \WeakMap<object, mixed>           $seen
     * @param list<array<array-key, mixed>>     $arrayStack
     */
    private static function snapshotValue(
        mixed               $value,
        \WeakMap            $seen = new \WeakMap,
        array &             $arrayStack = [],
        null|SnapshotBudget $budget = null,
        int                 $depth = 0,
    ): mixed {
        $budget ??= self::resolveBudget(null, null);

        if (! $budget->consume()) {
            return self::TRUNCATED_NODES;
        }

        if ($depth > $budget->maxDepth) {
            return self::TRUNCATED_DEPTH;
        }

        if (\is_array($value)) {
            return self::snapshotArray($value, $seen, $arrayStack, $budget, $depth);
        }

        if (\is_object($value)) {
            return self::snapshotObject($value, $seen, $arrayStack, $budget, $depth);
        }

        if (\is_resource($value)) {
            return self::describeResource($value);
        }

        if (\str_starts_with(\gettype($value), 'resource')) {
            return '[resource: closed]';
        }

        return $value;
    }

    /**
     * @param mixed  $value
     *
     * @return PhpType
     */
    private static function phpType(
        mixed $value,
    ): string {
        $type = \gettype($value);

        if (\str_starts_with($type, 'resource')) {
            return 'resource';
        }

        return match ($type) {
            'array', 'boolean', 'double', 'integer', 'NULL', 'object', 'string' => $type,
            default                                                             => 'string',
        };
    }

    /**
     * @param array<array-key, mixed>           $array
     * @param \WeakMap<object, mixed>           $seen
     * @param list<array<array-key, mixed>>     $arrayStack
     *
     * @return array<array-key, mixed>|string
     */
    private static function snapshotArray(
        array &        $array,
        \WeakMap       $seen,
        array &        $arrayStack,
        SnapshotBudget $budget,
        int            $depth,
    ): array|string {
        /** @noinspection PhpLoopCanBeReplacedWithStdFunctionCallsInspection */
        foreach ($arrayStack as $frame) {
            if ($frame === $array) {
                return self::ARRAY_RECURSION;
            }
        }
        unset($frame);

        $arrayStack[] = &$array;

        try {
            $copy      = [];
            $nextDepth = $depth + 1;

            foreach ($array as $key => &$item) {
                if (! $budget->hasNodesRemaining()) {
                    $copy[$key] = self::TRUNCATED_NODES;
                    break;
                }

                if (\is_array($item)) {
                    if ($nextDepth > $budget->maxDepth) {
                        $copy[$key] = self::TRUNCATED_DEPTH;
                    } elseif (self::isArrayOnStack($item, $arrayStack)) {
                        $copy[$key] = self::ARRAY_RECURSION;
                    } else {
                        if (! $budget->consume()) {
                            $copy[$key] = self::TRUNCATED_NODES;
                            break;
                        }
                        $copy[$key] = self::snapshotArray(
                            $item,
                            $seen,
                            $arrayStack,
                            $budget,
                            $nextDepth,
                        );
                    }
                } else {
                    $copy[$key] = self::snapshotValue(
                        $item,
                        $seen,
                        $arrayStack,
                        $budget,
                        $nextDepth,
                    );
                }
            }
            unset($item);

            return $copy;
        } finally {
            \array_pop($arrayStack);
        }
    }

    /**
     * @param array<array-key, mixed>       $array
     * @param list<array<array-key, mixed>> $arrayStack
     *
     * @return bool
     */
    private static function isArrayOnStack(
        array $array,
        array $arrayStack,
    ): bool {
        return \in_array($array, $arrayStack, true);
    }

    /**
     * @param \WeakMap<object, mixed>       $seen
     * @param list<array<array-key, mixed>> $arrayStack
     *
     * @return string|array{
     *     class: class-string,
     *     message: string,
     *     code: int|string,
     *     file: string,
     *     line: int
     * }|array{
     *     class: class-string,
     *     properties: array<string, mixed>
     * }|\DateTimeImmutable
     */
    private static function snapshotObject(
        object         $value,
        \WeakMap       $seen,
        array &        $arrayStack,
        SnapshotBudget $budget,
        int            $depth,
    ): string|array|\DateTimeImmutable {
        if ($seen->offsetExists($value)) {
            return '[Recursion: ' . $value::class . ']';
        }

        if ($value instanceof \Closure) {
            return self::describeClosure($value);
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            $copy = \DateTimeImmutable::createFromInterface($value);
            $seen->offsetSet($value, $copy);

            return $copy;
        }

        if ($value instanceof \Throwable) {
            $copy = [
                'class'   => $value::class,
                'message' => $value->getMessage(),
                'code'    => $value->getCode(),
                'file'    => $value->getFile(),
                'line'    => $value->getLine(),
            ];
            $seen->offsetSet($value, $copy);

            return $copy;
        }

        // Deep reflection can jump memory — skip when headroom is already thin.
        $remaining = MemoryManager::getMemoryRemaining();
        if ($remaining && $remaining < ( self::CUSHION_BYTES * 4 )) {
            $copy = self::uncloneable($value);
            $seen->offsetSet($value, $copy);

            return $copy;
        }

        $seen->offsetSet($value, true);

        try {
            $reflection = new \ReflectionClass($value);
            $properties = [];
            $nextDepth  = $depth + 1;

            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $name = $property->getName();

                if (! $property->isInitialized($value)) {
                    $properties[$name] = '[uninitialized]';
                    continue;
                }

                $propValue = $property->getValue($value);
                $redaction = Redaction::for($value, $name, $property);

                if ($redaction !== null) {
                    $properties[$name] = Redaction::mask(
                        $propValue,
                        $redaction['secret'],
                        $redaction['tags'],
                        $value,
                    );
                    continue;
                }

                $properties[$name] = self::snapshotValue(
                    $propValue,
                    $seen,
                    $arrayStack,
                    $budget,
                    $nextDepth,
                );
            }

            foreach (\get_object_vars($value) as $name => $propValue) {
                if (\array_key_exists($name, $properties)) {
                    continue;
                }

                $properties[$name] = self::snapshotValue(
                    $propValue,
                    $seen,
                    $arrayStack,
                    $budget,
                    $nextDepth,
                );
            }

            $copy = [
                'class'      => $value::class,
                'properties' => $properties,
            ];
            $seen->offsetSet($value, $copy);

            return $copy;
        } catch (\Throwable) {
            $copy = self::uncloneable($value);
            $seen->offsetSet($value, $copy);

            return $copy;
        }
    }

    /**
     * @param resource $resource
     */
    private static function describeResource(
        mixed $resource,
    ): string {
        $type = \get_resource_type($resource);

        return "[resource: {$type}]";
    }

    private static function describeClosure(
        \Closure $closure,
    ): string {
        try {
            $ref  = new \ReflectionFunction($closure);
            $name = $ref->getName();

            // PHP 8.4+ embeds location in anonymous names ({closure:file:line}, {closure:Class::method():line}).
            if (\str_starts_with($name, '{closure')) {
                return $name;
            }

            $file = $ref->getFileName();
            $line = $ref->getStartLine();

            if ($file === false || $file === null || $line === false) {
                return $name; // first-class callable / internal
            }

            return "{$name}@{$file}:{$line}";
        } catch (\Throwable) {
            return '[Closure]';
        }
    }

    private static function uncloneable(
        object $value,
    ): string {
        return '[Uncloneable: ' . $value::class . ']';
    }
}

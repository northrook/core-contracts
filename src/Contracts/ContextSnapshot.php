<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use JsonSerializable;
use Stringable;
use UnitEnum;

/**
 * Immutable snapshot of a context value for logging and exception payloads.
 *
 * Records the source {@see gettype()} and, where possible, a detached copy of the value.
 * Values that cannot be copied are replaced with a descriptive string.
 *
 * @phpstan-type PhpType 'array'|'boolean'|'double'|'integer'|'NULL'|'object'|'resource'|'string'
 */
final readonly class ContextSnapshot implements JsonSerializable, Stringable
{
    /**
     * @param PhpType  $type
     * @param mixed    $value
     */
    public function __construct(
        public string $type,
        public mixed $value,
    ) {}

    public static function from(
        mixed $value,
    ): self {
        return new self(
            self::phpType($value),
            self::snapshotValue($value),
        );
    }

    /**
     * @param list<mixed> $values
     *
     * @return list<self>
     */
    public static function parse(
        array $values,
    ): array {
        $snapshots = [];

        foreach ($values as $value) {
            $snapshots[] = self::from($value);
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
            : '[Unserializable ContextSnapshot]';
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
     * @param \WeakMap<object, mixed> $seen
     */
    private static function snapshotValue(
        mixed $value,
        \WeakMap $seen = new \WeakMap(),
    ): mixed {
        if (\is_array($value)) {
            return self::snapshotArray($value, $seen);
        }

        if (\is_object($value)) {
            return self::snapshotObject($value, $seen);
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
     * @param array<mixed> $array
     * @param \WeakMap<object, mixed> $seen
     *
     * @return array<mixed>
     */
    private static function snapshotArray(
        array $array,
        \WeakMap $seen,
    ): array {
        $copy = [];

        foreach ($array as $key => $item) {
            $copy[$key] = self::snapshotValue($item, $seen);
        }

        return $copy;
    }

    /**
     * @param \WeakMap<object, mixed> $seen
     */
    private static function snapshotObject(
        object $value,
        \WeakMap $seen,
    ): mixed {
        if ($seen->offsetExists($value)) {
            return $seen->offsetGet($value);
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

        $copy = self::tryCopyObject($value);

        if (\is_string($copy)) {
            return $copy;
        }

        $seen->offsetSet($value, $copy);

        return $copy;
    }

    /**
     * @return object|string
     */
    private static function tryCopyObject(
        object $value,
    ): object|string {
        $serialized = \serialize($value);

        try {
            $copy = \unserialize(
                $serialized,
                ['allowed_classes' => true],
            );
        } catch (\Throwable) {
            $copy = null;
        }

        if (\is_object($copy)) {
            return $copy;
        }

        if (new \ReflectionClass($value)->isCloneable()) {
            return clone $value;
        }

        return self::uncloneable($value);
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
        $ref  = new \ReflectionFunction($closure);
        $name = $ref->getName();

        if ($name === '{closure}') {
            $name = 'anonymous Closure';
        }

        $file = $ref->getFileName() ?? '?';
        $line = $ref->getStartLine();

        return "{$name}@{$file}:{$line}";
    }

    private static function uncloneable(
        object $value,
    ): string {
        return '[Uncloneable: ' . $value::class . ']';
    }
}

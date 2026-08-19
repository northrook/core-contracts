<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Mutable key-value map with insertion-ordered entries.
 *
 * Keys may be any PHP value (including objects). Equality is strict `===`,
 * with `NAN` treated as equal to itself. Unlike a native array, `0` and `'0'`
 * are distinct keys — there is no PHP key coercion.
 *
 * Objects are keyed by identity ({@see \spl_object_id()}); the map holds a
 * strong reference to object keys for as long as the entry exists.
 *
 * Arrays, non-`NAN` floats, and resources are accepted but use linear lookup.
 *
 * @template TKey
 * @template TValue
 *
 * @implements \ArrayAccess<TKey, TValue>
 * @implements \IteratorAggregate<TKey, TValue>
 */
final class Map implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @var list<array{key: TKey, value: TValue}>
     */
    private array $entries = [];

    /**
     * Fast path for indexable keys → entry offset.
     *
     * @var array<string, int<0, max>>
     */
    private array $index = [];

    /** Current number of entries. */
    public int $size {
        get => \count($this->entries);
    }

    /** Whether the map holds no entries. */
    public bool $isEmpty {
        get => $this->entries === [];
    }

    /**
     * First value in insertion order, or `null` when empty.
     *
     * @var TValue|null
     */
    public mixed $first {
        get => $this->entries === [] ? null : $this->entries[0]['value'];
    }

    /**
     * Last value in insertion order, or `null` when empty.
     *
     * @var TValue|null
     */
    public mixed $last {
        get => $this->entries === [] ? null : $this->entries[\array_key_last($this->entries)]['value'];
    }

    /**
     * @param iterable<TKey, TValue> $entries
     */
    public function __construct(
        iterable $entries = [],
    ) {
        foreach ($entries as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Independent shallow copy (object identity of keys/values is preserved).
     *
     * @return self<TKey, TValue>
     */
    public function copy(): self
    {
        return clone $this;
    }

    /**
     * Replaces all entries.
     *
     * @param iterable<TKey, TValue> $entries
     *
     * @return self<TKey, TValue>
     */
    public function assign(
        iterable $entries,
    ): self {
        $this->clear();

        foreach ($entries as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Sets {@see $key} to {@see $value}.
     *
     * Updating an existing key keeps its insertion position.
     *
     * @param TKey   $key
     * @param TValue $value
     *
     * @return self<TKey, TValue>
     */
    public function set(
        mixed $key,
        mixed $value,
    ): self {
        $offset = $this->locateKey($key);

        if ($offset !== null) {
            $this->entries[$offset]['value'] = $value;

            return $this;
        }

        $this->entries[] = ['key' => $key, 'value' => $value];
        $this->indexKey($key, \array_key_last($this->entries));

        return $this;
    }

    /**
     * Value for {@see $key}, or {@see $default} when absent.
     *
     * @param TKey   $key
     * @param TValue $default
     *
     * @return ($default is null ? TValue|null : TValue)
     */
    public function get(
        mixed $key,
        mixed $default = null,
    ): mixed {
        $offset = $this->locateKey($key);

        return $offset === null
            ? $default
            : $this->entries[$offset]['value'];
    }

    /**
     * Ensures {@see $key} exists, creating it via {@see $factory} when absent.
     *
     * @param TKey          $key
     * @param callable(): TValue $factory
     *
     * @return TValue
     */
    public function resolve(
        mixed    $key,
        callable $factory,
    ): mixed {
        $offset = $this->locateKey($key);

        if ($offset === null) {
            $value = $factory();
            $this->set($key, $value);

            return $value;
        }

        return $this->entries[$offset]['value'];
    }

    /**
     * Whether {@see $key} exists.
     *
     * Pass {@see $ofValue} (including `null`) to also require a strict value match.
     *
     * @param TKey   $key
     * @param TValue $ofValue
     */
    public function has(
        mixed    $key,
        mixed ...$ofValue,
    ): bool {
        $offset = $this->locateKey($key);

        if ($offset === null) {
            return false;
        }

        return $ofValue === [] || $this->entries[$offset]['value'] === $ofValue[0];
    }

    /**
     * Removes {@see $key} when present.
     *
     * @param TKey $key
     *
     * @return bool `true` if a value existed and was removed
     */
    public function delete(
        mixed $key,
    ): bool {
        $offset = $this->locateKey($key);

        if ($offset === null) {
            return false;
        }

        \array_splice($this->entries, $offset, 1);
        $this->reindex();

        return true;
    }

    /**
     * Merges entries from {@see $entries}.
     *
     * When {@see $override} is `false`, existing keys are left untouched.
     *
     * @param iterable<TKey, TValue> $entries
     *
     * @return self<TKey, TValue>
     */
    public function merge(
        iterable $entries,
        bool     $override = true,
    ): self {
        foreach ($entries as $key => $value) {
            if (! $override && $this->locateKey($key) !== null) {
                continue;
            }

            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Removes all entries.
     *
     * @return self<TKey, TValue>
     */
    public function clear(): self
    {
        $this->entries = [];
        $this->index   = [];

        return $this;
    }

    /**
     * @return list<TKey>
     */
    public function keys(): array
    {
        return \array_column($this->entries, 'key');
    }

    /**
     * @return list<TValue>
     */
    public function values(): array
    {
        return \array_column($this->entries, 'value');
    }

    /**
     * Insertion-ordered `[key, value]` pairs.
     *
     * @return list<array{key: TKey, value: TValue}>
     */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Whether every entry satisfies {@see $predicate}.
     *
     * @param callable(TValue, TKey): bool $predicate
     */
    public function every(
        callable $predicate,
    ): bool {
        return \array_all(
            $this->entries,
            static fn(array $entry): bool => $predicate($entry['value'], $entry['key']),
        );
    }

    /**
     * Whether any entry satisfies {@see $predicate}.
     *
     * @param callable(TValue, TKey): bool $predicate
     */
    public function any(
        callable $predicate,
    ): bool {
        return \array_any(
            $this->entries,
            static fn(array $entry): bool => $predicate($entry['value'], $entry['key']),
        );
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * @return \Traversable<TKey, TValue>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->entries as $entry) {
            yield $entry['key'] => $entry['value'];
        }
    }

    /**
     * JSON as a list of `{key, value}` objects (object keys become `{}` unless {@see \JsonSerializable}).
     *
     * @return list<array{key: TKey, value: TValue}>
     */
    public function jsonSerialize(): array
    {
        return $this->entries;
    }

    public function offsetExists(
        mixed $offset,
    ): bool {
        return $this->locateKey($offset) !== null;
    }

    /**
     * @return TValue|null
     */
    public function offsetGet(
        mixed $offset,
    ): mixed {
        return $this->get($offset);
    }

    /**
     * @param TKey   $offset
     * @param TValue $value
     */
    public function offsetSet(
        mixed $offset,
        mixed $value,
    ): void {
        if ($offset === null) {
            throw new InvalidArgumentException(
                message: 'Map entries require a key; use set($key, $value).',
                context: [
                    'name'     => 'offset',
                    'expected' => 'mixed key',
                    'received' => null,
                ],
            );
        }

        $this->set($offset, $value);
    }

    public function offsetUnset(
        mixed $offset,
    ): void {
        $this->delete($offset);
    }

    /**
     * Ensures cloned maps do not share internal storage with the original.
     */
    public function __clone(): void
    {
        $this->entries = [...$this->entries];
        $this->index   = [...$this->index];
    }

    /**
     * @return null|int<0, max>
     */
    private function locateKey(
        mixed $key,
    ): null|int {
        $lookup = $this->lookupKey($key);

        if ($lookup !== null) {
            return $this->index[$lookup] ?? null;
        }

        return \array_find_key(
            $this->entries,
            static fn(array $entry): bool => $entry['key'] === $key,
        );
    }

    /**
     * @param int<0, max> $offset
     */
    private function indexKey(
        mixed $key,
        int   $offset,
    ): void {
        $lookup = $this->lookupKey($key);

        if ($lookup !== null) {
            $this->index[$lookup] = $offset;
        }
    }

    private function reindex(): void
    {
        $this->index = [];

        foreach ($this->entries as $offset => $entry) {
            $this->indexKey($entry['key'], $offset);
        }
    }

    /**
     * Derives a lookup key for indexable map keys.
     *
     * Returns `null` for arrays, non-`NAN` floats, and resources (linear fallback).
     */
    private function lookupKey(
        mixed $key,
    ): null|string {
        return match (\gettype($key)) {
            'string'  => "s:$key",
            'integer' => "i:$key",
            'NULL'    => 'n:null',
            'boolean' => 'b:' . ( $key ? 'true' : 'false' ),
            'object'  => 'o:' . \spl_object_id($key),
            'double'  => \is_nan($key) ? 'd:NAN' : null,
            default   => null,
        };
    }
}

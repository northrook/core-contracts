<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Mutable, insertion-ordered record backed by a native PHP array.
 *
 * Keys use native PHP array semantics: integer-like strings may be coerced to
 * integers, so `0` and `'0'` address the same entry.
 *
 * ArrayAccess offsets are trusted as {@see TKey}; callers and static analysis
 * must ensure keys are `int|string`.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements \ArrayAccess<TKey, TValue>
 * @implements \IteratorAggregate<TKey, TValue>
 */
final class Record implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * @var array<TKey, TValue>
     */
    private array $entries = [];

    /** Current number of entries. */
    public int $size {
        get => \count($this->entries);
    }

    /** Whether the record holds no entries. */
    public bool $isEmpty {
        get => $this->entries === [];
    }

    /**
     * First value in insertion order, or `null` when empty.
     *
     * @var TValue|null
     */
    public mixed $first {
        get => $this->entries === []
            ? null
            : $this->entries[\array_key_first($this->entries)];
    }

    /**
     * Last value in insertion order, or `null` when empty.
     *
     * @var TValue|null
     */
    public mixed $last {
        get => $this->entries === []
            ? null
            : $this->entries[\array_key_last($this->entries)];
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
     * Independent shallow copy.
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
     * Sets a value without changing an existing key's insertion position.
     *
     * @param TKey   $key
     * @param TValue $value
     *
     * @return self<TKey, TValue>
     */
    public function set(
        int|string $key,
        mixed      $value,
    ): self {
        $this->entries[$key] = $value;

        return $this;
    }

    /**
     * Returns the value for a key, or the default when absent.
     *
     * @template TDefault
     *
     * @param TKey $key
     * @param TDefault $default
     *
     * @return TValue|TDefault
     */
    public function get(
        int|string $key,
        mixed      $default = null,
    ): mixed {
        return \array_key_exists($key, $this->entries)
            ? $this->entries[$key]
            : $default;
    }

    /**
     * Creates and stores a value when the key is absent.
     *
     * Stored `null` values are considered present.
     *
     * @param TKey               $key
     * @param callable(): TValue $factory
     *
     * @return TValue
     */
    public function resolve(
        int|string $key,
        callable   $factory,
    ): mixed {
        if (! \array_key_exists($key, $this->entries)) {
            $this->entries[$key] = $factory();
        }

        return $this->entries[$key];
    }

    /**
     * Whether a key exists.
     *
     * Pass a value, including `null`, to also require a strict value match.
     *
     * @param TKey   $key
     * @param TValue ...$ofValue
     */
    public function has(
        int|string $key,
        mixed ...  $ofValue,
    ): bool {
        if (! \array_key_exists($key, $this->entries)) {
            return false;
        }

        return $ofValue === [] || $this->entries[$key] === $ofValue[0];
    }

    /**
     * Removes a key when present.
     *
     * @param TKey $key
     *
     * @return bool `true` if an entry existed and was removed
     */
    public function delete(
        int|string $key,
    ): bool {
        if (! \array_key_exists($key, $this->entries)) {
            return false;
        }

        unset($this->entries[$key]);

        return true;
    }

    /**
     * Merges entries into the record.
     *
     * Existing keys retain their insertion position.
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
            if (! $override && \array_key_exists($key, $this->entries)) {
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

        return $this;
    }

    /**
     * @return list<TKey>
     */
    public function keys(): array
    {
        return \array_keys($this->entries);
    }

    /**
     * @return list<TValue>
     */
    public function values(): array
    {
        return \array_values($this->entries);
    }

    /**
     * Returns a shallow copy of the underlying associative array.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * @return \ArrayIterator<TKey, TValue>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->entries);
    }

    /**
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): array
    {
        return $this->entries;
    }

    public function offsetExists(
        mixed $offset,
    ): bool {
        /** @var TKey $offset */
        return \array_key_exists($offset, $this->entries);
    }

    /**
     * @return TValue|null
     */
    public function offsetGet(
        mixed $offset,
    ): mixed {
        /** @var TKey $offset */
        return \array_key_exists($offset, $this->entries)
            ? $this->entries[$offset]
            : null;
    }

    /**
     * @param TValue $value
     */
    public function offsetSet(
        mixed $offset,
        mixed $value,
    ): void {
        /** @var TKey $offset */
        $this->entries[$offset] = $value;
    }

    public function offsetUnset(
        mixed $offset,
    ): void {
        /** @var TKey $offset */
        unset($this->entries[$offset]);
    }
}

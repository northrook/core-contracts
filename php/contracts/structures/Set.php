<?php

declare(strict_types=1);

namespace Northrook;

/**
 * An ordered collection of unique values.
 *
 * Values are compared with strict `===` equality.
 *
 * Insertion order is preserved and can be repositioned explicitly via {@see append()} and {@see prepend()}.
 *
 * Array offsets accept integers and numeric strings, `$set[0]` and `$set['0']` are equivalent.
 *
 * Malformed offsets throw {@see \OutOfRangeException}.
 *
 * Non-scalar offset types throw {@see \InvalidArgumentException}.
 *
 * @template T
 *
 *
 *
 * @implements \ArrayAccess<int|string, T>
 * @implements \IteratorAggregate<non-negative-int, T>
 */
class Set implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * Ordered sequence of unique entries.
     *
     * @var array<non-negative-int, T>
     */
    private array $entries = [];

    /**
     * Membership lookup for indexable values.
     *
     * Arrays, floats, and resources are not indexed and fall back to linear search.
     *
     * @var array<string, true>
     */
    private array $index = [];

    /**
     * Creates a new {@see Set} from the given values.
     *
     * Each value is passed to {@see add()}.
     *
     * @param array<T> $values
     */
    public function __construct(
        array $values = [],
    ) {
        $this->add(...$values);
    }

    /**
     * Creates an independent copy of this set.
     *
     * Entry values are shallow-copied; object identity is preserved.
     */
    final public function copy(): static
    {
        return clone $this;
    }

    /**
     * Appends values that are not already present.
     *
     * Existing entries are left in place.
     *
     * @param T ...$values
     */
    public function add(
        ...$values,
    ): static {
        foreach ($values as $value) {
            if ($this->has($value)) {
                continue;
            }

            $this->entries[] = $value;
            $this->indexValue($value);
        }

        return $this;
    }

    /**
     * Merges values from an iterable into this set.
     *
     * When `$override` is `false`, each value uses {@see add()} semantics: new entries are appended and existing entries stay in place.
     *
     * When `$override` is `true`, each value uses {@see append()} semantics: existing entries are relocated to the end in merge order.
     *
     * @param iterable<T> $values
     */
    public function merge(
        iterable $values,
        bool     $override = false,
    ): static {
        foreach ($values as $value) {
            if ($override) {
                $this->append($value);
            }
            else {
                $this->add($value);
            }
        }

        return $this;
    }

    /**
     * Ensures each value appears at the end of the sequence.
     *
     * Values already present are removed from their current position first.
     *
     * @param T ...$values
     */
    public function append(
        ...$values,
    ): static {
        foreach ($values as $value) {
            $this->delete($value);
            $this->entries[] = $value;
            $this->indexValue($value);
        }

        return $this;
    }

    /**
     * Ensures each value appears at the front of the sequence.
     *
     * Values already present are removed from their current position first.
     *
     * Argument order is preserved in the prepended segment.
     *
     * @param T ...$values
     */
    public function prepend(
        ...$values,
    ): static {
        foreach (array_reverse($values) as $value) {
            $this->delete($value);
            \array_unshift($this->entries, $value);
            $this->indexValue($value);
        }

        return $this;
    }

    /**
     * Reorders entries according to the comparator.
     *
     * Membership is unchanged; only sequence order is affected.
     *
     * @param callable(T, T): int $sorter
     */
    public function sort(
        callable $sorter,
    ): static {
        \usort($this->entries, $sorter);

        return $this;
    }

    /**
     * Removes all given values from the set.
     *
     * @param T ...$values
     */
    public function remove(
        ...$values,
    ): static {
        foreach ($values as $value) {
            $this->delete($value);
        }

        return $this;
    }

    /**
     * Removes a single value from the set.
     *
     * @return bool `true` if the value was present and removed.
     */
    public function delete(
        mixed $value,
    ): bool {
        $index = $this->locateValue($value);

        if ($index === null) {
            return false;
        }

        $this->unindexValue($value);
        \array_splice($this->entries, $index, 1);

        return true;
    }

    /**
     * Checks whether a value exists in the set.
     *
     * Uses strict `===` equality.
     *
     * @param T $value
     */
    public function has(
        mixed $value,
    ): bool {
        $key = $this->lookupKey($value);

        if ($key !== null) {
            return isset($this->index[$key]);
        }

        if (\is_float($value) && \is_nan($value)) {
            return array_any(
                $this->entries,
                static fn(mixed $entry): bool => \is_float($entry) && \is_nan($entry),
            );
        }

        return \in_array($value, $this->entries, true);
    }

    /**
     * Checks whether all given values exist in the set.
     *
     * @param T ...$values
     */
    public function contains(
        ...$values,
    ): bool {
        return \array_all(
            $values,
            fn($value) => $this->has($value),
        );
    }

    /**
     * Serializes the set as an ordered list of values.
     *
     * @return list<T>
     */
    public function jsonSerialize(): array
    {
        return [...$this->entries];
    }

    /**
     * Applies a callback to each entry and returns a new set of the results.
     *
     * Mapped values are inserted via {@see add()} semantics: duplicates are skipped without repositioning, so the first mapped occurrence of each value is kept.
     *
     * @template U
     *
     * @param callable(T): U $callback
     *
     * @return static<U>
     */
    final public function map(
        callable $callback,
    ): static {
        return new static(\array_map($callback, $this->entries));
    }

    /**
     * Returns a new set containing entries that pass the callback.
     *
     * When no callback is given, only `null` and empty strings are removed.
     *
     * Other values such as `false`, `0`, and `0.0` are preserved.
     *
     * @param null|callable(T): bool $callback
     *
     * @return static<T>
     */
    final public function filter(
        null|callable $callback = null,
    ): static {
        /** @var list<T> $filtered */
        $filtered = \array_values(\array_filter(
            $this->entries,
            $callback ?? static fn(mixed $value): bool => $value !== null && $value !== '',
        ));

        return new static($filtered);
    }

    /**
     * Removes all entries and resets internal state.
     */
    public function clear(): static
    {
        $this->entries = [];
        $this->index   = [];

        return $this;
    }

    /**
     * Returns a copy of all entries in insertion order.
     *
     * @return list<T>
     */
    final public function values(): array
    {
        return [...$this->entries];
    }

    /**
     * The current number of entries in the set.
     *
     * @return non-negative-int
     */
    final public function size(): int
    {
        return \count($this->entries);
    }

    /**
     * Whether the set contains no entries.
     */
    final public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * Retrieves the first entry in the sequence.
     *
     * @return T
     *
     * @throws \UnderflowException If the set is empty.
     */
    final public function first()
    {
        if ($this->entries === []) {
            throw new \UnderflowException(
                message: 'Set is empty.',
            );
        }

        return $this->entries[0];
    }

    /**
     * Retrieves the last entry in the sequence.
     *
     * @return T
     *
     * @throws \UnderflowException If the set is empty.
     */
    final public function last()
    {
        if ($this->entries === []) {
            throw new \UnderflowException(
                message: 'Set is empty.',
            );
        }

        return $this->entries[\array_key_last($this->entries)];
    }

    /**
     * @return non-negative-int
     */
    final public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * Iterate over entries in insertion order.
     *
     * @return \ArrayIterator<non-negative-int, T>
     */
    final public function getIterator(): \ArrayIterator
    {
        /** @var \ArrayIterator<non-negative-int, T> $iterator */
        $iterator = new \ArrayIterator($this->entries);
        return $iterator;
    }

    /**
     * Ensures cloned sets do not share internal storage with the original.
     */
    final public function __clone(): void
    {
        $this->entries = [...$this->entries];
        $this->index   = [...$this->index];
    }

    /**
     * @return array{entries: array<non-negative-int, T>}
     */
    public function __serialize(): array
    {
        return ['entries' => $this->entries];
    }

    /**
     * @param array{entries: array<non-negative-int, T>} $data
     */
    public function __unserialize(
        array $data,
    ): void {
        $this->entries = $data['entries'];
        $this->index   = [];

        foreach ($this->entries as $value) {
            $this->indexValue($value);
        }
    }

    /**
     * Checks whether an offset exists in the sequence.
     *
     * Numeric string offsets (e.g. `'0'`) are cast to integers.
     *
     * @param non-negative-int|numeric-string $offset
     */
    final public function offsetExists(
        mixed $offset,
    ): bool {
        $index = $this->normalizeOffset($offset);

        return \array_key_exists($index, $this->entries);
    }

    /**
     * Retrieves the entry at an offset.
     *
     * Numeric string offsets (e.g. `'0'`) are cast to integers.
     *
     * @param non-negative-int|numeric-string $offset
     *
     * @return T
     *
     * @throws \OutOfRangeException If the offset is invalid or does not exist.
     */
    final public function offsetGet(
        mixed $offset,
    ): mixed {
        $index = $this->normalizeOffset($offset);

        if (! \array_key_exists($index, $this->entries)) {
            throw new \OutOfRangeException(
                message: "Offset $index does not exist.",
            );
        }

        return $this->entries[$index];
    }

    /**
     * Appends a value via {@see add()}.
     *
     * Only null offsets are supported (`$set[] = $value`).
     *
     * @param null|non-negative-int|numeric-string $offset
     * @param T $value
     *
     * @throws \OutOfRangeException If a non-null offset is given.
     */
    final public function offsetSet(
        mixed $offset,
        mixed $value,
    ): void {
        if ($offset !== null) {
            $this->normalizeOffset($offset);

            throw new \OutOfRangeException('Set entries cannot be assigned by offset.');
        }

        $this->add($value);
    }

    /**
     * Removes the entry at an offset.
     *
     * Numeric string offsets (e.g. `'0'`) are cast to integers.
     *
     * @param non-negative-int|numeric-string $offset
     *
     * @throws \OutOfRangeException If the offset is malformed.
     */
    final public function offsetUnset(
        mixed $offset,
    ): void {
        $index = $this->normalizeOffset($offset);

        if (! \array_key_exists($index, $this->entries)) {
            return;
        }

        $this->unindexValue($this->entries[$index]);

        \array_splice($this->entries, $index, 1);
    }

    /**
     * Locates the sequence index of a value.
     *
     * `NAN` is handled separately because {@see \array_search()} uses strict `===`.
     *
     * @return null|non-negative-int
     */
    private function locateValue(
        mixed $value,
    ): null|int {
        $index = \array_search(
            needle  : $value,
            haystack: $this->entries,
            strict  : true,
        );

        if ($index !== false) {
            return $index;
        }

        if (\is_float($value) && \is_nan($value)) {
            return \array_find_key(
                $this->entries,
                static fn(mixed $entry): bool => \is_float($entry) && \is_nan($entry),
            );
        }

        return null;
    }

    /**
     * Registers a value in the membership index.
     */
    private function indexValue(
        mixed $value,
    ): void {
        $key = $this->lookupKey($value);

        if ($key !== null) {
            $this->index[$key] = true;
        }
    }

    /**
     * Removes a value from the membership index.
     */
    private function unindexValue(
        mixed $value,
    ): void {
        $key = $this->lookupKey($value);

        if ($key !== null) {
            unset($this->index[$key]);
        }
    }

    /**
     * Derives a lookup key for indexable values.
     *
     * Returns `null` for arrays, floats, and resources.
     */
    private function lookupKey(
        mixed $value,
    ): null|string {
        return match (\gettype($value)) {
            'string'  => "s:$value",
            'integer' => "i:$value",
            'NULL'    => 'n:null',
            'boolean' => 'b:' . ( $value ? 'true' : 'false' ),
            'object'  => 'o:' . \spl_object_id($value),
            'double'  => \is_nan($value) ? 'd:NAN' : null,
            default   => null,
        };
    }

    /**
     * Normalizes an array offset to a zero-based integer index.
     *
     * Numeric string offsets are cast to integers, matching native array behaviour.
     *
     * @return non-negative-int
     *
     * @throws \InvalidArgumentException If the offset is not an integer or string.
     * @throws \OutOfRangeException If the offset is not a non-negative integer or numeric string.
     */
    private function normalizeOffset(
        mixed $offset,
    ): int {
        if (\is_int($offset)) {
            if ($offset < 0) {
                throw new \OutOfRangeException(
                    message: 'Offset must not be negative; `int` provided.',
                );
            }

            /** @var int<0, max> */
            return $offset;
        }

        if (\is_string($offset)) {
            if ($offset === '' || \strspn($offset, '0123456789') !== \strlen($offset)) {
                throw new \OutOfRangeException(
                    message: 'Invalid offset; `string` provided.',
                );
            }

            /** @var int<0, max> */
            return (int) $offset;
        }

        $type = \get_debug_type($offset);

        throw new \InvalidArgumentException(
            message: "Offset must be an integer or numeric string; `{$type}` provided.",
        );
    }
}

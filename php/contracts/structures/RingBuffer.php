<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Fixed-size circular buffer in most-recent-first order.
 *
 * Automatically overwrites the oldest entry when capacity is reached.
 *
 * @template T
 *
 * @implements \IteratorAggregate<int, T>
 */
final class RingBuffer implements \Countable, \IteratorAggregate
{
    /** @var array<int, T|null> */
    private array $buffer;

    /** @var int<0, max> */
    private int $count = 0;

    /** @var int<0, max> Write index for the next {@see push()}. */
    private int $index = 0;

    /**
     * Most recent entry, or `null` when empty.
     *
     * @var T|null
     */
    public mixed $newest {
        get => $this->at(0);
    }

    /**
     * Oldest entry still retained, or `null` when empty.
     *
     * @var T|null
     */
    public mixed $oldest {
        get => $this->at($this->count - 1);
    }

    /** Write index for the next {@see push()}. */
    public int $offset {
        get => $this->index;
    }

    /** Current number of retained entries. */
    public int $size {
        get => $this->count;
    }

    /** Whether the buffer is at full capacity. */
    public bool $isFull {
        get => $this->count === $this->capacity;
    }

    /**
     * @param int<1, max> $capacity Maximum number of entries.
     */
    public function __construct(
        public readonly int $capacity,
    ) {
        if ($capacity < 1) {
            throw new InvalidArgumentException(
                message: 'RingBuffer capacity must be a positive integer greater than 0.',
                context: [
                    'name'     => 'capacity',
                    'expected' => 'int<1, max>',
                    'received' => $capacity,
                ],
            );
        }

        $this->buffer = \array_fill(0, $capacity, null);
    }

    /**
     * Adds an entry. When full, the oldest entry is overwritten.
     *
     * @param T $entry
     *
     * @return array{entry: T, overwritten: bool}
     */
    public function push(
        mixed $entry,
    ): array {
        $overwritten = $this->isFull;

        $this->buffer[$this->index] = $entry;
        $this->index                = ( $this->index + 1 ) % $this->capacity;

        if ($this->count < $this->capacity) {
            $this->count++;
        }

        return [
            'entry'       => $entry,
            'overwritten' => $overwritten,
        ];
    }

    /**
     * Entry at a most-recent-first offset, or `null` when out of range.
     *
     * @return T|null
     */
    public function at(
        int $index,
    ): mixed {
        if (! $this->validOffset($index)) {
            return null;
        }

        return $this->buffer[$this->next($index)];
    }

    /**
     * Whether {@see $value} exists anywhere in the buffer (most-recent-first).
     *
     * @param T $value
     */
    public function has(
        mixed $value,
        bool  $strict = true,
    ): bool {
        return \array_any(
            $this->values(),
            $strict
                ? static fn(mixed $entry): bool => $entry === $value
                : static fn(mixed $entry): bool => $entry == $value,
        );
    }

    /** Clears all entries and resets internal cursors. */
    public function clear(): void
    {
        $this->buffer = \array_fill(0, $this->capacity, null);
        $this->index  = 0;
        $this->count  = 0;
    }

    /**
     * All retained entries in most-recent-first order.
     *
     * @return list<T>
     */
    public function values(
        bool $reverse = false,
    ): array {
        $values = \iterator_to_array($this, false);

        return $reverse
            ? ( $values |> \array_reverse(...) )
            : $values;
    }

    /**
     * Most-recent-first `[offset => entry]` pairs.
     *
     * @return \Generator<int, T|null>
     */
    public function entries(): \Generator
    {
        for ($offset = 0; $offset < $this->count; $offset++) {
            yield $offset => $this->at($offset);
        }
    }

    /**
     * @return \Traversable<int, T>
     */
    public function getIterator(): \Traversable
    {
        for ($offset = 0; $offset < $this->count; $offset++) {
            /** @var T $entry */
            $entry = $this->at($offset);
            yield $entry;
        }
    }

    /**
     * Whether every entry satisfies {@see $predicate} (most-recent-first).
     *
     * @param callable(T|null, int): bool $predicate
     */
    public function every(
        callable $predicate,
    ): bool {
        return \array_all(
            $this->values(),
            static fn(mixed $entry, int $index): bool => $predicate($entry, $index),
        );
    }

    public function count(): int
    {
        return $this->count;
    }

    /** Circular index for most-recent-first offset {@see $offset}. */
    private function next(
        int $offset,
    ): int {
        return ( $this->index - 1 - $offset + $this->capacity ) % $this->capacity;
    }

    private function validOffset(
        int $number,
    ): bool {
        return $number >= 0 && $number < $this->count;
    }
}

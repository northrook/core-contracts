<?php

declare(strict_types=1);

namespace Northrook\Contracts\Parameter;

use Northrook\Contracts\InvalidArgumentException;

/**
 * @implements \IteratorAggregate<non-empty-lowercase-string, non-empty-string>
 */
final class Tags implements \Countable, \IteratorAggregate
{
    /**
     * @var array<non-empty-lowercase-string, non-empty-string>
     */
    private array $tags = [];

    /**
     * @var non-empty-string[]
     */
    public array $value {
        get => $this->tags;
    }

    /**
     * @param string|list<string>  $tags
     */
    public function __construct(
        string|array $tags = [],
    ) {
        if ($tags !== []) {
            $this->add(...\is_array($tags) ? $tags : [$tags]);
        }
    }

    public function count(): int
    {
        return \count($this->tags);
    }

    /**
     * @return \Traversable<non-empty-lowercase-string, non-empty-string>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->tags;
    }

    public function add(
        string ...$tags,
    ): self {
        foreach ($tags as $tag) {
            if ($tag === '' || \trim($tag) !== $tag) {
                throw new InvalidArgumentException(
                    message: 'Parameter Tag must be a non-empty string, with no bracketing whitespace.',
                    context: ['tags' => $tags, 'tag' => $tag],
                );
            }

            $key = \strtolower($tag);

            $this->tags[$key] ??= $tag;
        }

        return $this;
    }

    public function set(
        string ...$tags,
    ): self {
        return $this->clear()->add(...$tags);
    }

    public function remove(
        string ...$tags,
    ): int {
        $removed = 0;

        foreach ($tags as $tag) {
            $key = \strtolower($tag);
            if (\array_key_exists($key, $this->tags)) {
                unset($this->tags[$key]);
                $removed++;
            }
        }

        return $removed;
    }

    public function clear(): self
    {
        $this->tags = [];
        return $this;
    }

    public function has(
        string ...$tags,
    ): bool {
        return \array_all(
            $tags,
            fn($tag) => \array_key_exists(
                \strtolower($tag),
                $this->tags,
            ),
        );
    }
}

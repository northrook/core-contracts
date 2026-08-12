<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * The base class for all parameter types.
 *
 * Runtime value shape is enforced by {@see Parameter\Type::from()} on assignment.
 *
 * @phpstan-type ParameterValue bool|float|int|string|\UnitEnum|null|array<array-key, mixed>
 */
abstract class Parameter extends Value
{
    /**
     * Internal tag collection.
     *
     * @var Parameter\Tags
     */
    final protected readonly Parameter\Tags $tagged;

    final private(set) Parameter\Type $type;

    /**
     * @var non-empty-string
     */
    final protected(set) string $key {
        get => $this->key;
        set(string $key) {
            Assert::validKey(
                value : $key,
                source: __METHOD__,
            );
            $this->key = \strtolower($key);
        }
    }

    final protected(set) mixed $value {
        get => $this->value;
        set(mixed $value) {
            $this->type  = Parameter\Type::from($value);
            $this->value = $value;
        }
    }

    /**
     * @var non-empty-string[]
     */
    final public array $tags {
        get => $this->tagged->value;
    }

    /**
     * @param non-empty-string          $key
     * @param ParameterValue            $value
     * @param null|string|Value\Secret  $secret
     * @param string|list<string>       $tags
     */
    public function __construct(
        string                                     $key,
        bool|float|int|string|null|\UnitEnum|array $value,
        null|string|Value\Secret                   $secret = null,
        string|array                               $tags = [],
    ) {
        parent::__construct(secret: $secret);
        $this->key    = $key;
        $this->value  = $value;
        $this->tagged = new Parameter\Tags($tags);
    }

    final public function isTagged(
        string ...$value,
    ): bool {
        return $this->tagged->has(...$value);
    }
}

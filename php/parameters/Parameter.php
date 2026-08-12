<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts\Parameter\Tags;
use Northrook\Contracts\Parameter\Type;

/**
 * The base class for all parameter types.
 *
 * Runtime value shape is enforced by {@see \Northrook\Contracts\Parameter\Type::from()} on assignment.
 *
 * @phpstan-import-type ParameterValue from ParameterInterface
 */

abstract class Parameter extends Value implements ParameterInterface
{
    /**
     * Internal tag collection.
     *
     * @var \Northrook\Contracts\Parameter\Tags
     */
    final protected readonly Tags $tagged;

    /**
     * @var \Northrook\Contracts\Parameter\Type
     */
    final private(set) Type $type;

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
            $this->type  = Type::from($value);
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

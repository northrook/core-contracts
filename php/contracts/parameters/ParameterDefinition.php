<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Container\Secret as SecretAttribute;
use Northrook\Contracts\Exportable;
use Northrook\Contracts\Serializable;
use Northrook\Parameter\Secret as SecretEnum;
use Northrook\Parameter\Type;
use Northrook\Parameter\Value;
use Northrook\Runtime\Assert;

/**
 * The mutable class for all parameter during Container compilation.
 *
 * @phpstan-import-type ParameterValue from \Northrook\Parameter
 * @phpstan-type SecretArgument null|"sensitive"|"credential"|\Northrook\Container\Secret|\Northrook\Parameter\Secret
 */
final class ParameterDefinition implements Serializable, Exportable
{
    use Serializer;

    /**
     * Soft lock flag.
     *
     * Critical: true means mutations must throw while `true`.
     */
    private(set) bool $immutable = false;

    /**
     * @var \Northrook\Parameter\Type
     */
    private(set) Type $type;

    private(set) null|SecretEnum $secret;

    /**
     * @var array<non-empty-lowercase-string, non-empty-string>
     */
    private(set) array $tags = [];

    /**
     * @var non-empty-lowercase-string
     */
    private(set) string $key;

    /**
     * @var ParameterValue
     */
    private(set) bool|float|int|string|null|\UnitEnum|array $value;

    /**
     * @param non-empty-string            $key
     * @param ParameterValue|\Stringable  $value
     * @param SecretArgument              $secret
     * @param string|list<string>         $tags
     * @param null|\Northrook\Parameter\Type   $type
     */
    public function __construct(
        string                                                 $key,
        bool|float|int|string|null|\UnitEnum|\Stringable|array $value = Value::Unset,
        null|string|SecretEnum|SecretAttribute                 $secret = null,
        string|array                                           $tags = [],
        null|Type                                              $type = null,
    ) {
        $this
            ->key($key)
            ->type($type ?? Type::resolve($value))
            ->value($value)
            ->secret($secret)
            ->tags(...is_string($tags) ? [$tags] : $tags);
    }

    /**
     * @return \Northrook\Parameter
     */
    public function getParameter(): Parameter
    {
        $this->freeze();

        $this->validate(
            $this->value,
            __METHOD__,
        );

        return new Parameter(
            key   : $this->key,
            value : $this->value,
            type  : $this->type,
            secret: $this->secret,
            tags  : $this->tags,
        );
    }

    /**
     * @param non-empty-string $set
     *
     * @return \Northrook\ParameterDefinition
     */
    public function key(
        mixed $set,
    ): self {
        $this->editable();
        Assert::validKey($set, source: __METHOD__);
        $this->key = \strtolower($set);
        return $this;
    }

    /**
     * @param \Northrook\Parameter\Type  $type
     * @param bool                       $validate
     *
     * @return \Northrook\ParameterDefinition
     */
    public function type(
        Type $type,
        bool $validate = false,
    ): self {
        $this->editable();
        $this->type = $type;

        if ($validate && isset($this->value)) {
            $this->validate($this->value, __METHOD__);
        }

        return $this;
    }

    /**
     * @param ParameterValue|\Stringable  $set
     * @param bool                        $validate
     *
     * @return \Northrook\ParameterDefinition
     */
    public function value(
        bool|float|int|string|null|\UnitEnum|\Stringable|array $set,
        bool                                                   $validate = false,
    ): self {
        $this->editable();

        if ($validate) {
            $this->validate($set, __METHOD__);
        }

        // Stringable is accepted for convenience; stored + validated as string.
        if ($set instanceof \Stringable) {
            $set = $set->__toString();
        }

        $this->value = $set;

        return $this;
    }

    /**
     * @param null|"sensitive"|"credential"|\Northrook\Parameter\Secret|\Northrook\Container\Secret  $secret
     *
     * @return \Northrook\ParameterDefinition
     */
    public function secret(
        null|string|SecretEnum|SecretAttribute $secret,
    ): self {
        $this->editable();

        if ($secret === null) {
            $this->secret = null;
        }
        else if ($secret instanceof SecretAttribute) {
            $this->secret = $secret->secret;
            $this->tags(...$secret->conditions);
        }
        else {
            $this->secret = SecretEnum::from($secret);
        }

        return $this;
    }

    /**
     * @param string ...$add
     *
     * @return \Northrook\ParameterDefinition
     */
    public function tags(
        string ...$add,
    ): self {
        $this->editable();

        foreach ($add as $tag) {
            if ($tag === '' || \trim($tag) !== $tag) {
                throw new InvalidArgumentException(
                    message: 'Parameter Tag must be a non-empty string, with no bracketing whitespace.',
                    context: ['tags' => $this->tags, 'tag' => $tag],
                );
            }

            $key = \strtolower($tag);

            $this->tags[$key] ??= $tag;
        }
        return $this;
    }

    /**
     * @param string ...$tags
     *
     * @return bool
     */
    public function hasTags(
        string ...$tags,
    ): bool {
        if (empty($tags)) {
            return false;
        }

        return \array_all(
            $tags,
            fn($tag) => \array_key_exists(
                \strtolower($tag),
                $this->tags,
            ),
        );
    }

    /**
     * @param string ...$tags
     *
     * @return int
     */
    public function removeTags(
        string ...$tags,
    ): int {
        $this->editable();

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

    /**
     * Clear all tags.
     *
     * @return \Northrook\ParameterDefinition
     */
    public function clearTags(): self
    {
        $this->editable();

        $this->tags = [];
        return $this;
    }

    /**
     * Soft-lock against further mutation.
     *
     * @param bool  $set
     *
     * @return \Northrook\ParameterDefinition
     */
    public function freeze(
        bool $set = true,
    ): self {
        $this->immutable = $set;
        return $this;
    }

    private function editable(): void
    {
        if ($this->immutable) {
            throw new LogicException(
                message: 'Cannot modify immutable parameter.',
                context: ['parameter' => $this],
            );
        }
    }

    /**
     * Eval-able PHP for the frozen {@see Parameter} DTO (not this mutable builder).
     */
    public function _export(): string
    {
        $this->guardExport();

        return $this->getParameter()->_export();
    }

    /**
     * @param mixed             $value
     * @param non-empty-string  $caller
     *
     * @return bool
     */
    private function validate(
        mixed  $value,
        string $caller,
    ): bool {
        if ($value !== Value::Unset && $this->type->validate($value)) {
            return true;
        }

        throw new InvalidArgumentException(
            message: 'Invalid value for parameter type ' . $this->type->name,
            context: [
                'caller' => $caller,
                'value'  => \debug_value_type($value),
                'type'   => $this->type,
            ],
        );
    }
}

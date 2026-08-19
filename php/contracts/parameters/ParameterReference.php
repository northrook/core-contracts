<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Container\Secret as SecretAttribute;
use Northrook\Contracts\Exportable;
use Northrook\Contracts\Serializable;
use Northrook\Parameter\Secret as SecretEnum;
use Northrook\Parameter\Type;
use Northrook\Runtime\Assert;

/**
 * The mutable class for all parameter during Container compilation.
 *
 * @phpstan-import-type ParameterValue from \Northrook\Parameter
 * @phpstan-type SecretArgument null|"sensitive"|"credential"|\Northrook\Container\Secret|\Northrook\Parameter\Secret
 */
final class ParameterReference implements Serializable, Exportable
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
    private(set) mixed $value;

    /**
     * @param non-empty-string           $key
     * @param ParameterValue             $value
     * @param SecretArgument            $secret
     * @param string|list<string>        $tags
     * @param \Northrook\Parameter\Type  $type
     */
    public function __construct(
        string                                     $key,
        bool|float|int|string|null|\UnitEnum|array $value,
        null|string|SecretEnum|SecretAttribute     $secret = null,
        string|array                               $tags = [],
        Type                                       $type = Type::Value,
    ) {
        $this
            ->key($key)
            ->value($value)
            ->secret($secret)
            ->tags(...is_string($tags) ? [$tags] : $tags)
            ->type($type);
    }

    public function __invoke(): Parameter
    {
        $this->freeze();

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
     * @return \Northrook\ParameterReference
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
     *
     * @return \Northrook\ParameterReference
     */
    public function type(
        Type $type,
    ): self {
        $this->editable();
        $this->type = $type;
        return $this;
    }

    /**
     * @param ParameterValue $set
     *
     * @return \Northrook\ParameterReference
     */
    public function value(
        mixed $set,
    ): self {
        $this->editable();
        Assert::validParameter($set, source: __METHOD__);
        $this->value = $set;
        return $this;
    }

    /**
     * @param null|"sensitive"|"credential"|\Northrook\Parameter\Secret|\Northrook\Container\Secret  $secret
     *
     * @return \Northrook\ParameterReference
     */
    public function secret(
        null|string|SecretEnum|SecretAttribute $secret,
    ): self {
        $this->editable();

        if ($secret === null) {
            $this->secret = null;
        } else if ($secret instanceof SecretAttribute) {
            $this->secret = $secret->secret;
            $this->tags(...$secret->conditions);
        } else {
            $this->secret = SecretEnum::from($secret);
        }

        return $this;
    }

    /**
     * @param string ...$add
     *
     * @return \Northrook\ParameterReference
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
     * @return \Northrook\ParameterReference
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
     * @return \Northrook\ParameterReference
     */
    public function freeze(): self
    {
        $this->immutable = true;
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

        return $this()->_export();
    }
}

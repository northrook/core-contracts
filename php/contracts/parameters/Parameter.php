<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Exportable;
use Northrook\Contracts\Serializable;
use Northrook\Parameter\Secret;
use Northrook\Parameter\Type;

/**
 * Immutable parameter value.
 *
 * - Used to store parameter values in the {@see \Northrook\ParameterMapInterface}.
 * - Provided by the {@see \Northrook\ContainerInterface}.
 * - Resolved using {@see \Northrook\ParameterDefinition}, during a {@see \Northrook\Container\CompilerPass}, using the {@see \Northrook\ParameterStoreInterface}.
 *
 * @phpstan-type ParameterValue bool|float|int|string|\UnitEnum|null|array<array-key, mixed>
 */
final readonly class Parameter implements Serializable, Exportable
{
    use Serializer;

    /**
     * @param non-empty-lowercase-string                           $key
     * @param ParameterValue                                       $value
     * @param \Northrook\Parameter\Type                            $type
     * @param \Northrook\Parameter\Secret|null                     $secret
     * @param array<non-empty-lowercase-string, non-empty-string>  $tags
     */
    public function __construct(
        public string                                     $key,
        public bool|float|int|string|\UnitEnum|array|null $value,
        public Type                                       $type,
        public Secret|null                                $secret,
        public array                                      $tags,
    ) {}

    /**
     * Determines if all provided tags exist in the collection of tags.
     *
     * @param string  ...$value list of tags to be checked.
     *
     * @return bool `true` if all the provided tags exist, otherwise `false`
     */
    public function isTagged(
        string ...$value,
    ): bool {
        if (empty($value)) {
            return false;
        }

        return \array_all(
            $value,
            fn($tag) => \array_key_exists(\strtolower($tag), $this->tags),
        );
    }

    public function _export(): string
    {
        $this->guardExport();

        return Export::class(
            self::class,
            $this->key,
            $this->value,
            $this->type,
            $this->secret,
            $this->tags,
        );
    }
}

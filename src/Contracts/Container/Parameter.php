<?php

declare(strict_types = 1);

namespace Northrook\Contracts\Container;

use InvalidArgumentException;

/**
 * A typed, optionally secret configuration value.
 *
 * Wraps a runtime value with its {@see gettype()} discriminator so the
 * container can validate and coerce parameters without losing type information.
 */
readonly class Parameter
{
    /**
     * @param 'array'|'boolean'|'double'|'integer'|'NULL'|'object'|'string' $type
     * @param bool                                                          $secret
     * @param null|string                                                   $string
     * @param null|bool                                                     $bool
     * @param null|array<array-key, mixed>                                  $array
     * @param null|int                                                      $int
     * @param null|float                                                    $float
     * @param null|object                                                   $object
     * @param null                                                          $null
     */
    private function __construct(
        public string $type,
        public bool $secret,
        public null|string $string = null,
        public null|bool $bool = null,
        public null|array $array = null,
        public null|int $int = null,
        public null|float $float = null,
        public null|object $object = null,
        public null $null = null,
    ) {}

    /**
     * @param mixed $value
     * @param bool  $secret
     *
     * @return Parameter
     */
    final public static function from(mixed $value, bool $secret = false): Parameter
    {
        return match (\gettype($value)) {
            'NULL'    => new self('NULL', $secret),
            'string'  => new self('string', $secret, string: $value),
            'boolean' => new self('boolean', $secret, bool: $value),
            'integer' => new self('integer', $secret, int: $value),
            'double'  => new self('double', $secret, float: $value),
            'array'   => new self('array', $secret, array: $value),
            'object'  => new self('object', $secret, object: $value),
            default   => throw new InvalidArgumentException('Unsupported Parameter type ' . \var_export($value, true)),
        };
    }

    public function is(mixed $type): bool
    {
        return $this->type === \gettype($type);
    }

    public function value(): mixed
    {
        return match ($this->type) {
            'string'  => $this->string,
            'boolean' => $this->bool,
            'integer' => $this->int,
            'double'  => $this->float,
            'array'   => $this->array,
            'object'  => $this->object,
            'NULL'    => null,
        };
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $of
     * @param bool            $nullable
     *
     * @return ($nullable is true ? null|T : T)
     */
    public function object(string $of, bool $nullable = false): null|object
    {
        if ($this->object instanceof $of) {
            return $this->object;
        }

        if ($nullable) {
            return null;
        }

        throw new InvalidArgumentException();
    }
}

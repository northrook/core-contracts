<?php

declare(strict_types=1);

namespace Core\Contracts\Container;

use InvalidArgumentException;

/**
 */
final readonly class Parameter
{
    /**
     * @param 'array'|'boolean'|'double'|'integer'|'NULL'|'object'|'string' $type
     * @param null|string                                                   $string
     * @param null|bool                                                     $bool
     * @param null|array<array-key, mixed>                                  $array
     * @param null|int                                                      $int
     * @param null|float                                                    $float
     * @param null|object                                                   $object
     * @param null                                                          $null
     */
    private function __construct(
        public string  $type,
        public ?string $string = null,
        public ?bool   $bool = null,
        public ?array  $array = null,
        public ?int    $int = null,
        public ?float  $float = null,
        public ?object $object = null,
        public null    $null = null,
    ) {}

    /**
     * @param mixed $value
     *
     * @return Parameter
     */
    public static function from( mixed $value ) : Parameter
    {
        return match ( \gettype( $value ) ) {
            'NULL'    => new self( 'NULL' ),
            'string'  => new self( 'string', string : $value ),
            'boolean' => new self( 'boolean', bool : $value ),
            'integer' => new self( 'integer', int : $value ),
            'double'  => new self( 'double', float : $value ),
            'array'   => new self( 'array', array : $value ),
            'object'  => new self( 'object', object : $value ),
            default   => throw new InvalidArgumentException(
                'Unsupported Parameter type '.\var_export( $value, true ),
            ),
        };
    }

    public function is( mixed $type ) : bool
    {
        return $this->type === \gettype( $type );
    }

    public function value() : mixed
    {
        return match ( $this->type ) {
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
    public function object(
        string $of,
        bool   $nullable = false,
    ) : ?object {
        if ( $this->object instanceof $of ) {
            return $this->object;
        }
        if ( $nullable ) {
            return null;
        }

        throw new InvalidArgumentException();
    }
}

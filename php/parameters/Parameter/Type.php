<?php

declare(strict_types=1);

namespace Northrook\Contracts\Parameter;

use Northrook\Contracts\OverflowException;
use Northrook\Contracts\TypeException;

/**
 * Parameter value type.
 *
 * Top-level and nested array elements must resolve to a case.
 */
enum Type
{
    /** Nesting limit for array resolution. */
    private const int MAX_DEPTH = 32;

    /** `null` values. */
    case Null;

    /** Boolean values. */
    case Boolean;

    /** Floating-point values. */
    case Float;

    /** Integer values. */
    case Integer;

    /** String values. */
    case String;

    /**
     * Empty and keyed array whose values are supported {@see Type}s.
     */
    case Array;

    /**
     * Non-empty list-shaped array whose values are supported {@see Type}s.
     */
    case List;

    /** Non-backed enum instance. */
    case UnitEnum;

    /** Int- or string-backed enum instance. */
    case BackedEnum;

    /**
     * Resolve `$value` to a {@see Type}.
     *
     * @throws TypeException if `$value` is unsupported
     */
    public static function from(
        mixed $value,
    ): self {
        $exception = null;

        try {
            $type = self::resolve($value, 0, true);
        } catch (OverflowException $exception) {
            $type = null;
        }

        if ($type !== null) {
            return $type;
        }

        $debug = \debug_value_type($value);

        throw new TypeException(
            message : 'Unsupported Parameter type: ' . $debug . '.',
            context : [
                'value' => $value,
                'type'  => $debug,
            ],
            previous: $exception,
        );
    }

    /**
     * Resolve `$value` to a {@see Type}, or `null` when unsupported.
     *
     * Never throws: nesting beyond {@see Type::MAX_DEPTH} yields `null`.
     */
    public static function tryFrom(
        mixed $value,
    ): null|self {
        return self::resolve($value, 0, false);
    }

    /**
     * Whether `$value` resolves to a supported {@see Type}.
     *
     * Soft check via {@see tryFrom()}; does not throw.
     */
    public static function validate(
        mixed $value,
    ): bool {
        return self::tryFrom($value) !== null;
    }

    /**
     * Resolve the top-level value to a {@see Type}.
     *
     * @param bool  $throw  throws {@see OverflowException} when `true`
     *
     * @throws OverflowException if depth exceeds {@see Type::MAX_DEPTH}
     */
    private static function resolve(
        mixed $value,
        int   $depth,
        bool  $throw,
    ): null|self {
        if ($depth > self::MAX_DEPTH) {
            if (! $throw) {
                return null;
            }

            throw new OverflowException(
                message: 'Maximum recursion depth exceeded.',
                context: [
                    'depth' => $depth,
                    'value' => $value,
                ],
            );
        }

        return match (gettype($value)) {
            'string'  => self::String,
            'integer' => self::Integer,
            'double'  => self::Float,
            'boolean' => self::Boolean,
            'NULL'    => self::Null,
            'array'   => self::resolveArray($value, $depth, $throw),
            'object'  => self::resolveObject($value),
            default   => null,
        };
    }

    /**
     * Validate `enum` objects.
     *
     * @return null|self::UnitEnum|self::BackedEnum
     */
    private static function resolveObject(
        object $value,
    ): null|self {
        return match (true) {
            $value instanceof \BackedEnum => self::BackedEnum,
            $value instanceof \UnitEnum => self::UnitEnum,
            default => null,
        };
    }

    /**
     * Validate nested arrays.
     *
     * Empty arrays are considered {@see Type::Array}.
     *
     * Rejects when any nested value is unsupported.
     *
     * @return null|Type::Array|Type::List
     */
    /**
     * @param array<array-key, mixed>  $value
     */
    private static function resolveArray(
        array $value,
        int   $depth,
        bool  $throw,
    ): null|self {
        if ($value === []) {
            return self::Array;
        }

        if (\array_any(
            array   : $value,
            callback: static fn($item) => ! self::resolve($item, $depth + 1, $throw),
        )) {
            return null;
        }

        return \array_is_list($value)
            ? self::List
            : self::Array;
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Argument;

use Northrook\InvalidArgumentException;

/**
 * Argument placeholder and required-type sentinel.
 *
 * Use a case as a parameter default. An omitted argument is still this enum;
 * a provided value never is (`instanceof self` means "not provided").
 *
 * ```
 * function bind(mixed $id = Value::String): string
 * {
 *     return Value::String->require($id, 'id');
 * }
 * ```
 *
 * {@see Unset} means provided, any type. Typed cases also check {@see matches()}.
 */
enum Value
{
    case Unset;
    case Null;

    case Bool;
    case True;
    case False;

    case Number;
    case Int;
    case Float;

    case String;
    case ClassString;

    case Array;
    case Object;

    case Key;

    /**
     * @phpstan-assert-if-true !self $value
     */
    public static function provided(
        mixed $value,
    ): bool {
        return ! $value instanceof self;
    }

    public function matches(
        mixed $value,
    ): bool {
        return match ($this) {
            self::Unset => false,
            self::Null => $value === null,
            self::Bool => \is_bool($value),
            self::True => $value === true,
            self::False => $value === false,
            self::Number => \is_int($value) || \is_float($value) || \is_numeric($value),
            self::Int => \is_int($value),
            self::Float => \is_float($value),
            self::String => \is_string($value),
            self::ClassString => \is_class_string($value),
            self::Array => \is_array($value),
            self::Object => \is_object($value),
            self::Key => \is_string($value) || \is_int($value),
        };
    }

    /**
     * Reject a leftover placeholder; typed cases also {@see matches()}.
     *
     * @throws InvalidArgumentException
     */
    public function require(
        mixed  $value,
        string $argument = 'value',
    ): mixed {
        if ($value instanceof self) {
            throw new InvalidArgumentException(
                message: "Required argument `{$argument}` is missing.",
                context: [
                    'argument' => $argument,
                    'expected' => $this,
                    'value'    => $value,
                ],
            );
        }

        if ($this !== self::Unset && ! $this->matches($value)) {
            throw new InvalidArgumentException(
                message: "Invalid argument `{$argument}`: expected {$this->name}, got " . \debug_value_type($value) . '.',
                context: [
                    'argument' => $argument,
                    'expected' => $this,
                    'value'    => \debug_value_type($value),
                ],
            );
        }

        return $value;
    }

    /**
     * Placeholder → `$default`. Otherwise same checks as {@see require()}.
     *
     * @throws InvalidArgumentException
     */
    public function optional(
        mixed $value,
        mixed $default = null,
    ): mixed {
        if ($value instanceof self) {
            return $default;
        }

        if ($this !== self::Unset) {
            $this->require($value);
        }

        return $value;
    }
}

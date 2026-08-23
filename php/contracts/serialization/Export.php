<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Exportable;
use Northrook\Contracts\Resettable;
use Northrook\Export\Constant;

/**
 * @static
 */
final class Export implements Resettable
{
    private const string INDENT = '    ';

    /** @var int<0, max> */
    private static int $depth = 0;

    private function __construct() {}

    public static function reset(): void
    {
        self::$depth = 0;
    }

    /**
     * Living identifier for nested {@see value()} / {@see array()} / {@see class()} graphs.
     *
     * Leading `\` is stripped. Magic `__…__` names stay unprefixed; all others get `\`.
     * The dump does not resolve or validate the identifier.
     *
     * @param string $name
     */
    public static function const(
        string $name,
    ): Constant {
        return Constant::export($name);
    }

    /**
     * @param class-string  $className
     * @param mixed         ...$arguments
     *
     * @return string
     */
    public static function class(
        string   $className,
        mixed ...$arguments,
    ): string {
        $class = '\\' . \trim($className, '\\');
        $close = self::pad();
        $item  = self::pad(1);
        $root  = self::$depth === 0;

        self::$depth++;

        try {
            $arguments = \array_map(
                static fn(mixed $argument): string => $item . Export::value($argument),
                $arguments,
            );
        } finally {
            self::$depth--;
        }

        if ($arguments === []) {
            $export = "new {$class}()";
        } else {
            $export = "new {$class}(\n" . \implode(",\n", $arguments) . ",\n{$close})";
        }

        return $root ? $export . ";\n" : $export;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return string
     */
    public static function array(
        array $value,
    ): string {
        return Export::value($value);
    }

    public static function value(
        mixed $value,
    ): string {
        if ($value instanceof Exportable) {
            return $value->_export();
        }

        if ($value instanceof \UnitEnum) {
            return '\\' . \ltrim(\var_export($value, true), '\\');
        }

        if ($value instanceof Constant) {
            return $value->constant;
        }

        if (\is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $align = 0;
            $items = [];
            $close = self::pad();
            $item  = self::pad(1);

            foreach (\array_keys($value) as $key) {
                $length = \is_int($key)
                    ? \strlen(\strval($key))
                    : \strlen($key) + 2;

                if ($length > $align) {
                    $align = $length;
                }
            }

            self::$depth++;

            try {
                foreach ($value as $key => $element) {
                    $index   = \is_int($key) ? \strval($key) : "'{$key}'";
                    $items[] = $item . \str_pad($index, $align) . ' => ' . Export::value($element);
                }
            } finally {
                self::$depth--;
            }

            return "[\n" . \implode(",\n", $items) . ",\n{$close}]";
        }

        return \var_export($value, true);
    }

    /**
     * @param int<0, max> $plus
     */
    private static function pad(
        int $plus = 0,
    ): string {
        return \str_repeat(self::INDENT, self::$depth + $plus);
    }
}

<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Exportable;

/**
 * @static
 */
final class Export
{
    private function __construct() {}

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
        $class  = '\\' . \trim($className, '\\');
        $export = \array_map(Export::value(...), $arguments);

        return "new {$class}(\n" . \implode(",\n", $export) . "\n);\n";
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

        if (\is_array($value)) {
            $align = 0;
            $array = [];

            foreach (array_keys($value) as $key) {
                $length = \is_int($key)
                    ? \strlen(\strval($key))
                    : \strlen($key) + 2;

                if ($length > $align) {
                    $align = $length;
                }
            }

            foreach ($value as $key => $item) {
                $index   = \is_int($key) ? \strval($key) : "'{$key}'";
                $array[] = \str_pad($index, $align) . ' => ' . Export::value($item);
            }

            return "[\n" . \implode(",\n", $array) . "\n]";
        }

        return \var_export($value, true);
    }
}

<?php

declare(strict_types=1);

namespace Northrook;

use \Northrook\Export\Exporter;
use Northrook\Contracts\Exportable;
use Northrook\Contracts\Resettable;
use Northrook\Export\Constant;
use Northrook\Export\RawString;
use Northrook\Logger\Log;
use Symfony\Component\VarExporter\VarExporter;

/**
 * @static
 */
final class Export implements Resettable
{
    private const string INDENT = '    ';

    /**
     * Spliced as double-quoted pieces. `\0` in single quotes is backslash + `0`.
     *
     * @var array<string, string>
     */
    private const array STRING_CONTROLS = [
        "\0"       => '\'."\\0".\'',
        "\r"       => '\'."\\r".\'',
        "\n"       => '\'."\\n".\'',
        "\u{202A}" => '\'."\\u{202A}".\'',
        "\u{202B}" => '\'."\\u{202B}".\'',
        "\u{202C}" => '\'."\\u{202C}".\'',
        "\u{202D}" => '\'."\\u{202D}".\'',
        "\u{202E}" => '\'."\\u{202E}".\'',
        "\u{2066}" => '\'."\\u{2066}".\'',
        "\u{2067}" => '\'."\\u{2067}".\'',
        "\u{2068}" => '\'."\\u{2068}".\'',
        "\u{2069}" => '\'."\\u{2069}".\'',
    ];

    private static null|Exporter $exporter = null;

    private static bool $failsafe = false;

    /** @var int<0, max> */
    private static int $depth = 0;

    private function __construct() {}

    public static function setFailsafe(
        bool $set = true,
    ): bool {
        return self::$failsafe = $set;
    }

    public static function exporter(): Exporter
    {
        return self::$exporter ??= Exporter::resolve();
    }

    public static function string(
        string $value,
    ): string {
        if ($value === '') {
            return "''";
        }

        $quoted = "'" . \addcslashes($value, "'\\") . "'";
        $string = \str_replace(
            \array_keys(self::STRING_CONTROLS),
            \array_values(self::STRING_CONTROLS),
            $quoted,
        );

        if ($string === $quoted) {
            return $string;
        }

        $string = \str_replace('.\'\'.', '.', $string);

        if (\str_starts_with($string, "''.")) {
            $string = \substr($string, 3);
        }

        if (\str_ends_with($string, ".''")) {
            $string = \substr($string, 0, -3);
        }

        return $string;
    }

    public static function value(
        mixed $value,
        bool  $isPayload = false,
    ): string {
        if (\is_int($value) || \is_float($value)) {
            return \var_export($value, true);
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if ($value === '') {
            return "''";
        }
        if ($value === []) {
            return '[]';
        }

        if (\is_string($value)) {
            return Export::string($value);
        }

        if ($value instanceof \UnitEnum) {
            return Export::enum($value);
        }

        if ($value instanceof RawString) {
            return $value->value;
        }

        if ($isPayload && ! \is_array($value)) {
            throw new InvalidArgumentException(
                message: 'Cannot export value of type ' . \get_debug_type($value) . ' as payload',
                context: ['value' => $value],
            );
        }

        return match (gettype($value)) {
            'array'  => Export::array($value, $isPayload),
            'object' => Export::object($value),
            default  => \var_export($value, true),
        };
    }

    public static function enum(
        \UnitEnum $value,
    ): string {
        return '\\' . \ltrim(\var_export($value, true), '\\');
    }

    public static function object(
        object $value,
    ): string {
        if ($value instanceof \UnitEnum) {
            return '\\' . \ltrim(\var_export($value, true), '\\');
        }

        if ($value instanceof Exportable) {
            return $value->_export();
        }

        if ($value instanceof Constant) {
            return $value->constant;
        }

        if ($value instanceof RawString) {
            return $value->value;
        }

        $exporter = static::exporter();

        if ($exporter === Exporter::Reflection) {
            return self::serializeClassState(
                $value::class,
                self::properties($value),
            );
        }

        try {
            return $exporter === Exporter::Symfony
                ? self::symfonyVarExporter($value)
                : self::deepclone($value);
        }
        catch (\Throwable $exception) {
            $exportException = new RuntimeException(
                message : 'Failed to export ' . $value::class . ' using ' . $exporter->value,
                context : [
                    'object'   => $value,
                    'exporter' => $exporter,
                ],
                previous: $exception,
            );
            if (self::$failsafe) {
                Log::alert($exportException);
                return self::serializeClassState($value::class, self::properties($value));
            }
            throw $exportException;
        }
    }

    private static function symfonyVarExporter(
        object $object,
    ): string {
        try {
            return VarExporter::export($object);
        }
        catch (\Throwable $exception) {
            throw new RuntimeException(
                message : 'Failed to export ' . $object::class . ' using ' . VarExporter::class,
                context : ['object' => $object],
                previous: $exception,
            );
        }
    }

    private static function deepclone(
        object $object,
    ): string {
        $payload = Export::array(\deepclone_to_array($object), true);
        return '\deepclone_from_array(' . $payload . ', null, true)';
    }

    /**
     * @return array<string, mixed> property name => value (declared, non-static, initialized)
     */
    private static function properties(
        object $object,
    ): array {
        return \array_map(
            static fn($property) => $property->getValue($object),
            Reflect::class($object)->getPropertiesMap(
                onlyInitialized: $object,
            ),
        );
    }

    /**
     * Living identifier for nested {@see value()} / {@see array()} / {@see class()} graphs.
     *
     * Leading `\` is stripped. Magic `__…__` names stay unprefixed; all others get `\`.
     * The dump does not resolve or validate the identifier.
     *
     * @param string  $name
     *
     * @return \Northrook\Export\Constant
     */
    public static function const(
        string $name,
    ): Constant {
        return Constant::export($name);
    }

    /**
     * Dump-time token that emits {@see $value} verbatim into generated PHP.
     *
     * Use when a nested graph already holds exported source (or a hand-written
     * expression) and must not be re-quoted by {@see string()} / {@see value()}.
     * The dump does not validate the source.
     *
     * @param string  $value  PHP source to emit.
     *
     * @return \Northrook\Export\RawString
     */
    public static function raw(
        string $value,
    ): RawString {
        return RawString::export($value);
    }

    /**
     * Eval-able `new Class(...)` dump. Named variadic keys emit as call-site named args.
     *
     * @param class-string  $className
     * @param mixed         ...$arguments
     *
     * @return string
     */
    public static function class(
        string   $className,
        mixed ...$arguments,
    ): string {
        return self::invocation(
            'new ' . self::className($className),
            $arguments,
        );
    }

    /**
     * Eval-able `Class::method(...)` dump. Named variadic keys emit as call-site named args.
     *
     * The dump does not resolve or validate that {@see $method} exists.
     *
     * @param class-string      $className
     * @param non-empty-string  $method
     * @param mixed             ...$arguments
     *
     * @return string
     */
    public static function call(
        string   $className,
        string   $method,
        mixed ...$arguments,
    ): string {
        if (! self::isNamedArgument($method)) {
            throw new InvalidArgumentException(
                message: 'Export::call method must be a valid PHP identifier.',
                context: ['method' => $method],
            );
        }

        return self::invocation(
            self::className($className) . '::' . $method,
            $arguments,
        );
    }

    /**
     * @param non-empty-string         $callee  `new \Class` or `\Class::method`
     * @param array<array-key, mixed>  $arguments
     */
    private static function invocation(
        string $callee,
        array  $arguments,
    ): string {
        $close = self::pad();
        $root  = self::$depth === 0;

        self::$depth++;

        try {
            $args = self::formatArguments($arguments);
        }
        finally {
            self::$depth--;
        }

        if ($args === []) {
            $export = "{$callee}()";
        }
        else {
            $export = "{$callee}(\n" . \implode(",\n", $args) . ",\n{$close})";
        }

        return $root ? $export . ";\n" : $export;
    }

    /**
     * @param array<array-key, mixed>  $arguments
     *
     * @return list<string>
     */
    private static function formatArguments(
        array $arguments,
    ): array {
        $item  = self::pad();
        $args  = [];
        $named = false;

        foreach ($arguments as $index => $argument) {
            if (\is_int($index)) {
                if ($named) {
                    throw new InvalidArgumentException(
                        message: 'Cannot mix named and positional arguments.',
                    );
                }

                $args[] = $item . Export::value($argument);
                continue;
            }

            if (! self::isNamedArgument($index)) {
                throw new InvalidArgumentException(
                    message: 'Named export arguments must be valid PHP identifiers.',
                    context: ['name' => $index],
                );
            }

            $named  = true;
            $args[] = $item . $index . ': ' . Export::value($argument);
        }

        return $args;
    }

    /**
     * @param class-string  $className
     *
     * @return non-empty-string
     */
    private static function className(
        string $className,
    ): string {
        return '\\' . \trim($className, '\\');
    }

    /**
     * @param array<array-key, mixed>  $value
     * @param bool                     $isPayload
     *
     * @return string
     */
    public static function array(
        array $value,
        bool  $isPayload = false,
    ): string {
        if ($value === []) {
            return '[]';
        }

        $align = 0;
        $items = [];
        $close = self::pad();
        $item  = self::pad(1);

        $indexes = [];

        foreach (\array_keys($value) as $key) {
            $index     = \is_int($key) ? \strval($key) : Export::string($key);
            $indexes[] = $index;
            $length    = \strlen($index);

            if ($length > $align) {
                $align = $length;
            }
        }

        self::$depth++;

        try {
            $i = 0;
            foreach ($value as $element) {
                $items[] = $item . \str_pad($indexes[$i++], $align) . ' => ' . Export::value($element, $isPayload);
            }
        }
        finally {
            self::$depth--;
        }

        return "[\n" . \implode(",\n", $items) . ",\n{$close}]";
    }

    public static function reset(): void
    {
        self::$exporter = null;
        self::$failsafe = false;
        self::$depth    = 0;
    }

    /**
     * @param int<0, max>  $plus
     */
    private static function pad(
        int $plus = 0,
    ): string {
        return \str_repeat(
            self::INDENT,
            self::$depth + $plus,
        );
    }

    public static function key(
        int|string $value,
    ): string {
        return \is_int($value)
            ? \strval($value)
            : Export::string($value);
    }

    /**
     * PHP named-argument identifier: letter/`_`/high-byte first, then alnum/`_`/high-byte.
     *
     * Uses `\A`/`\z` (not `^`/`$`) so a trailing newline cannot match.
     */
    private static function isNamedArgument(
        string $name,
    ): bool {
        return \preg_match('/\A[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*\z/', $name) === 1;
    }

    /**
     * @param class-string            $class
     * @param array<array-key,mixed>  $state
     *
     * @return non-empty-string
     */
    private static function serializeClassState(
        string $class,
        array  $state,
    ): string {
        $class = '\\' . \ltrim($class, '\\');
        $close = self::pad();
        $item  = self::pad(1);

        $pairs = [];
        self::$depth++;
        try {
            foreach ($state as $name => $value) {
                $pairs[] = $item . Export::key($name) . ' => ' . Export::value($value);
            }
        }
        finally {
            self::$depth--;
        }

        $array = $pairs === []
            ? '[]'
            : "[\n" . \implode(",\n", $pairs) . ",\n{$close}]";

        return "\instantiate({$class}::class, {$array})";
    }
}

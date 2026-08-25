<?php

declare(strict_types=1);

namespace Northrook;

use \Northrook\Export\Exporter;
use Northrook\Contracts\Exportable;
use Northrook\Contracts\Resettable;
use Northrook\Export\Constant;
use Northrook\Logger\Log;
use Symfony\Component\VarExporter\VarExporter;

/**
 * @static
 */
final class Export implements Resettable
{
    private const string INDENT = '    ';

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

        // TODO : Look into porting the Symfony VarExporter later

        return "'" . \addcslashes($value, "'\\") . "'";
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

    public static function object(
        object $object,
    ): string {

        if ($object instanceof \UnitEnum) {
            return '\\' . \ltrim(\var_export($object, true), '\\');
        }

        if ($object instanceof Exportable) {
            return $object->_export();
        }

        if ($object instanceof Constant) {
            return $object->constant;
        }
        
        $exporter = static::exporter();

        if ($exporter === Exporter::Reflection) {
            return self::serializeClassState(
                $object::class,
                self::properties($object),
            );
        }

        try {
            return $exporter === Exporter::Symfony
                ? self::symfonyVarExporter($object)
                : self::deepclone($object);
        }
        catch (\Throwable $exception) {
            $exportException = new RuntimeException(
                message : 'Failed to export ' . $object::class . ' using ' . $exporter->value,
                context : [
                    'object'   => $object,
                    'exporter' => $exporter,
                ],
                previous: $exception,
            );
            if (self::$failsafe) {
                Log::alert($exportException);
                return self::serializeClassState($object::class, self::properties($object));
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
        }
        finally {
            self::$depth--;
        }

        if ($arguments === []) {
            $export = "new {$class}()";
        }
        else {
            $export = "new {$class}(\n" . \implode(",\n", $arguments) . ",\n{$close})";
        }

        return $root ? $export . ";\n" : $export;
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

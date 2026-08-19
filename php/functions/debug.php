<?php

declare(strict_types=1);

use Northrook\Context;

/**
 * Compact debug descriptor for logs and exception messages.
 *
 * ⚠️ `$append_scalar` may dump sensitive data.
 *
 * Examples: `string:5:value`, `string:empty`, `list:2`, `object:stdClass#1`.*
 */
function debug_value_type(
    mixed $from,
    bool  $append_scalar = false,
): string {
    $type = match (gettype($from)) {
        'string'            => 'string:' . ( $from === '' ? 'empty' : \mb_strlen($from) ),
        'integer'           => 'integer:' . \strlen(strval($from)),
        'double'            => 'float:' . \strlen(strval($from)),
        'boolean'           => 'bool:' . ( $from ? 'true' : 'false' ),
        'NULL'              => 'null',
        'array'             => ( \array_is_list($from) ? 'list:' : 'array:' ) . \count($from),
        'object' => match (true) {
            $from instanceof \UnitEnum => 'enum:' . $from::class . '::' . $from->name,
            $from instanceof \Closure => 'closure' . \spl_object_id($from),
            default => 'object:' . \spl_object_id($from),
        },
        'resource'          => 'resource:' . ( \get_resource_type($from) ?: 'unknown' ),
        'resource (closed)' => 'resource:closed',
        default             => 'type:unknown',
    };

    if (! $append_scalar || ! \is_scalar($from) || empty($from) || is_bool($from)) {
        return $type;
    }

    if (Context::isUntrusted()) {
        throw new RuntimeException(
            message: 'Attempted to dump sensitive data in untrusted context.',
        );
    }

    return "{$type}.{$from}";
}

/**
 * Dump values.
 *
 * Uses {@see \Northrook\Debug::dump} if available, otherwise {@see var_dump}.
 *
 * @param mixed  ...$values
 *
 * @return void
 */
function debug_dump(
    mixed ...$values,
): void {
    if (class_exists('\Northrook\Debug::dump')) {
        \Northrook\Debug::dump(...$values);
        return;
    }

    if (class_exists('\Tracy\Dumper')) {
        $settings = ['location' => false, 'theme' => 'dark'];
        $class    = 'class="tracy-' . $settings['theme'] . '"';
        $style    = 'style="font-family: monospace; padding: .25em;"';
        foreach ($values as $label => $value) {
            if (! Context::CLI) {
                if (\is_string($label)) {
                    echo "<div {$class} {$style}><strong>{$label}</strong></div>";
                }

                echo '<div style="padding-block-start: .25em"></div>';
            }
            Tracy\Dumper::dump($value, $settings);
        }
        return;
    }

    $styles = [];
    foreach ([
        'margin'      => 0,
        'padding'     => '.25em',
        'font-family' => 'monospace',
        'overflow'    => 'scroll',
    ] as $property => $value) {
        $styles[] = "$property: $value";
    }
    $style = 'style="' . \implode('; ', $styles) . '"';
    foreach ($values as $name => $value) {
        $label = \is_string($name) ? "$name" : false;

        if ($label) {
            echo "<details open><summary style=\"cursor: pointer\">dump : $label</summary>";
        }
        echo "<xmp {$style}>";
        var_dump($value);
        echo '</xmp>';

        if ($label) {
            echo '</details>';
        }
    }
}

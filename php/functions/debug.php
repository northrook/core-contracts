<?php

declare(strict_types=1);

/**
 * Compact debug descriptor for logs and exception messages.
 *
 * Examples: `string:3`, `string:empty`, `list:2`, `object:stdClass#1`.
 */
function debug_value_type(
    mixed $from,
): string {
    return match (gettype($from)) {
        'string'            => 'string:' . ( $from === '' ? 'empty' : \mb_strlen($from) ),
        'integer'           => 'integer:' . \strlen(strval($from)),
        'double'            => 'float:' . \strlen(strval($from)),
        'boolean'           => 'bool:' . ( $from ? 'true' : 'false' ),
        'NULL'              => 'null',
        'array'             => ( \array_is_list($from) ? 'list:' : 'array:' ) . \count($from),
        'object'            => 'object:' . \get_class($from) . '#' . \spl_object_id($from),
        'resource'          => 'resource:' . ( \get_resource_type($from) ?: 'unknown' ),
        'resource (closed)' => 'resource:closed',
        default             => 'type:unknown',
    };
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

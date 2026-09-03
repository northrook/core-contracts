<?php

declare(strict_types=1);

/**
 * @template T of mixed
 *
 * @param T $value
 *
 * @return ( T is array ? T : array<int, T>)
 */
function as_array(
    mixed $value,
): array {
    return is_array($value) ? $value : [$value];
}

/**
 * @template T
 *
 * @param callable(T $value, array-key &$key): T  $callback
 * @param T                                      ...$values
 *
 * @return array<array-key, T>
 */
function array_from(
    callable $callback,
    mixed ...$values,
): array {
    $array = [];

    foreach ($values as $item) {
        $key         = \count($array);
        $value       = $callback($item, $key);
        $array[$key] = $value;
    }

    return $array;
}

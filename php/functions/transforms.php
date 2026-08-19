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

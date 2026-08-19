<?php

declare(strict_types=1);

/**
 * Check if a string is a valid class-string shape.
 *
 * Does not check if the class exists.
 *
 * @param mixed  $value
 *
 * @return bool
 *
 * @phpstan-assert-if-true class-string $value
 */
function is_class_string(
    mixed $value,
): bool {
    if (! \is_string($value)) {
        return false;
    }

    $exists = \class_exists($value, false);

    if ($exists) {
        return true;
    }

    if (! \match_charset($value, \CHARSET_NAMESPACE)) {
        return false;
    }

    $sample = \ltrim($value, '\\');

    if ($sample === '') {
        return false;
    }

    foreach (\explode('\\', $sample) as $fragment) {
        if ($fragment !== '' && match_charset($fragment[0], \CHARSET_ALPHA . '_')) {
            continue;
        }

        return false;
    }

    return true;
}

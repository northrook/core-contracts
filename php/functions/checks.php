<?php

declare(strict_types=1);

/**
 * @param mixed $value
 * @return bool
 *
 * @phpstan-assert-if-true scalar|null $value
 */
function is_primitive(
    mixed $value,
): bool {
    return \is_scalar($value) || $value === null;
}
/**
 * Check if a string is a valid class name.
 *
 * If the class cannot be loaded, this function will return `false`.
 *
 * @param mixed $value
 *
 * @return bool
 *
 * @phpstan-assert-if-true class-string $value
 */
function is_valid_class(
    mixed $value,
): bool {
    try {
        return \is_string($value) && \class_exists($value);
    }
    catch (\Throwable) {
        return false;
    }
}

/**
 * Whether `$value` is an `object` or `class-string` that satisfies every required part.
 *
 * - Only `object` and `class-string` are parsed.
 * - Anything else, a missing part list, or a failed `is_a` check is `false`.
 *
 * @param mixed $value
 * @param mixed ...$composedOf
 *
 * @return bool
 *
 * @phpstan-assert-if-true object|class-string $value
 */
function is_class(
    mixed    $value,
    mixed ...$composedOf,
): bool {
    if ($composedOf === [] || ( ! \is_object($value) && ! \is_string($value) )) {
        return false;
    }

    foreach ($composedOf as $type) {
        if (\is_object($type)) {
            $expected = $type::class;
        }
        elseif (\is_string($type) && $type !== '') {
            $expected = $type;
        }
        else {
            return false;
        }

        if (! \is_a($value, $expected, true)) {
            return false;
        }
    }

    return true;
}

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

/**
 * @param mixed $value
 * @return bool
 *
 * @phpstan-assert-if-true non-empty-string $value
 */
function is_non_empty_string(
    mixed $value,
): bool {
    return \is_string($value) && $value !== '';
}

/**
 * @param mixed $value
 * @return bool
 *
 * @phpstan-assert-if-true non-empty-lowercase-string $value
 */
function is_non_empty_lowercase_string(
    mixed $value,
): bool {
    return \is_string($value) && $value !== '' && \strtolower($value) === $value;
}

/**
 * Check if a string is a plausible filesystem path shape.
 *
 * Separators are normalized (`\` → {@see DIR_SEP}) before checking.
 * Scheme / drive matching is case-insensitive. Does not check existence,
 * absoluteness, or file vs directory.
 *
 * Rejects URI / URL shapes (`scheme://…`, and multi-letter `scheme:…` tokens
 * such as `mailto:` / `data:`) while allowing Windows drive roots (`C:`).
 *
 * @param mixed $value
 *
 * @return bool
 *
 * @phpstan-assert-if-true non-empty-string $value
 */
function is_path_string(
    mixed $value,
): bool {
    if (! \is_string($value) || $value === '') {
        return false;
    }

    if (\strlen($value) > \MAX_PATH_LENGTH || \str_contains($value, "\0")) {
        return false;
    }

    $path = \strtr($value, '\\', \DIR_SEP);

    if (\DIR_SEP !== '/') {
        $path = \str_replace('/', \DIR_SEP, $path);
    }

    $schemeEnd = \strpos($path, '://');

    if ($schemeEnd !== false && \is_path_scheme(\substr($path, 0, $schemeEnd))) {
        return false;
    }

    // Multi-letter URI schemes without `://` (`mailto:`, `data:`, …).
    // Single-letter `X:` is a Windows drive root — keep it.
    $colon = \strpos($path, ':');

    if ($colon !== false && $colon > 1) {
        $prefix = \substr($path, 0, $colon);

        if (! \str_contains($prefix, \DIR_SEP) && \is_path_scheme($prefix)) {
            return false;
        }
    }

    return true;
}

<?php

declare(strict_types=1);

function dir_path(
    string|Stringable $string,
    bool              $trailingSeparator = false,
    bool              $throwOnTraversal = false,
): string {
    if (\trim((string) $string) !== (string) $string) {
        throw new \Northrook\InvalidArgumentException(
            message: 'Path strings cannot be bracketed by whitespace',
            context: ['string' => $string],
        );
    }

    $path = \strtr((string) $string, '\\', \DIR_SEP);

    $prefix   = \str_starts_with($path, \DIR_SEP) ? \DIR_SEP : null;
    $suffix   = $trailingSeparator ? \DIR_SEP : null;
    $segments = [];

    foreach (\explode(\DIR_SEP, $path) as $index => $fragment) {
        if ($index === 0 && $fragment === '.') {
            $prefix = '.' . \DIR_SEP;
            continue;
        }

        if ($fragment === '..' && $throwOnTraversal) {
            throw new \Northrook\InvalidArgumentException(
                message: 'Path string contains illegal traversal',
                context: ['string' => $string],
            );
        }

        $segments[] = $fragment;
    }

    return $prefix . \implode(\DIR_SEP, $segments) . $suffix;
}

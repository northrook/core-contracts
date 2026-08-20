<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Filesystem\Path;
use Northrook\Reference\Href;
use Northrook\Reference\Uri;
use Northrook\Reference\Url;
use Northrook\Runtime\Assert;
use Stringable;

/**
 * Path / URL string normalizers.
 *
 * Cosmetic and structural rewrites only — not RFC validators
 * ({@see Assert::validUrl()}, {@see Assert::validUri()}, {@see Assert::validHref()}) and not typed
 * {@see Path} / {@see Uri} / {@see Url} / {@see Href} constructors.
 */
final class Normalize
{
    /**
     * Normalize a filesystem path string.
     *
     * Accepts a single path, a {@see Stringable}, or an array of segments to join.
     * Separators are unified to {@see \DIR_SEP}; empty and `.` segments are dropped;
     * duplicate separators collapse. Stream wrappers, UNC roots, POSIX absolutes,
     * `./` relatives, and Windows drive roots are preserved as prefixes.
     *
     * When `$traversal` is `true`, `..` segments resolve against the segment stack
     * and never pop above a rooted prefix (`/`, `//`, `X:/`, `scheme://…`). Leading
     * `..` on unrooted relative paths are kept. When `false`, `..` is left literal.
     *
     * ```
     * Normalize::path('assets\\\/scripts///app.js');
     * // => 'assets/scripts/app.js'
     *
     * Normalize::path(['/var', 'www', '../log'], traversal: true);
     * // => '/var/log'
     *
     * Normalize::path('C:\\Users\\..\\Windows', traversal: true);
     * // => 'C:/Windows'
     * ```
     *
     * @param null|array<null|string|Stringable>|string|Stringable $path
     * @param bool                                                 $traversal          Resolve `..` segments
     * @param bool                                                 $trailingSeparator  `true` ensure trailing {@see \DIR_SEP}; `false` strip it (roots like `/` kept)
     * @param bool                                                 $throwOnEmpty       Throw instead of returning `''`
     *
     * @return string
     *
     * @throws InvalidArgumentException When `$throwOnEmpty` is set and the result is empty
     * @throws RuntimeException When the result exceeds {@see \MAX_PATH_LENGTH}
     */
    public static function path(
        null|string|Stringable|array $path,
        bool                         $traversal = false,
        bool                         $trailingSeparator = false,
        bool                         $throwOnEmpty = false,
    ): string {
        if ($path === null || $path === '' || $path === []) {
            return (
                $throwOnEmpty
                    ? throw new InvalidArgumentException(
                        message: 'Cannot normalize an empty path.',
                        context: [
                            'name'     => 'path',
                            'expected' => 'non-empty path',
                            'received' => $path,
                        ],
                    )
                    : ''
            );
        }

        if (\is_array($path)) {
            $segments = [];

            foreach ($path as $part) {
                if ($part === null) {
                    continue;
                }

                $part = (string) $part;

                if ($part !== '') {
                    $segments[] = $part;
                }
            }

            $path = \implode(\DIR_SEP, $segments);
        } else {
            $path = (string) $path;
        }

        if ($path === '') {
            return (
                $throwOnEmpty
                    ? throw new InvalidArgumentException(
                        message: 'Cannot normalize an empty path.',
                        context: [
                            'name'     => 'path',
                            'expected' => 'non-empty path',
                            'received' => $path,
                        ],
                    )
                    : ''
            );
        }

        // Unify separators before root detection (drive / UNC / absolute).
        $path = \strtr($path, '\\', \DIR_SEP);

        [$prefix, $body] = self::pathRoot($path);

        // Rooted paths may still resolve `..` — never above the preserved prefix.
        $rooted = $prefix !== '' && $prefix !== './';

        $segments = [];

        foreach (\explode(\DIR_SEP, $body) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($traversal && $segment === '..') {
                if ($segments !== [] && \end($segments) !== '..') {
                    \array_pop($segments);
                    continue;
                }

                // Above-root for absolute / drive / UNC / wrapper: drop the `..`.
                if ($rooted) {
                    continue;
                }
            }

            $segments[] = $segment;
        }

        $normalized = self::trailingSeparator(
            $prefix . \implode(\DIR_SEP, $segments),
            $trailingSeparator,
        );

        if ($normalized === '') {
            return (
                $throwOnEmpty
                    ? throw new InvalidArgumentException(
                        message: 'Path normalized to an empty string.',
                        context: [
                            'name'     => 'path',
                            'expected' => 'non-empty path',
                            'received' => $path,
                        ],
                    )
                    : ''
            );
        }

        Assert::validPathLength($normalized);

        return $normalized;
    }

    /**
     * Unify `\` and `/` to {@see \DIR_SEP} without collapsing segments.
     *
     * Stream-wrapper / URL schemes (`scheme://…`) keep the scheme token intact;
     * only the portion after `://` is rewritten. Duplicate separators are left as-is
     * — use {@see path()} for full path canonicalization.
     *
     * @param null|string|Stringable $path
     * @param bool                   $trailingSeparator `true` ensure trailing {@see \DIR_SEP}; `false` strip it
     *
     * @return string
     */
    public static function slashes(
        null|string|Stringable $path = null,
        bool                   $trailingSeparator = false,
    ): string {
        $path = (string) ( $path ?? '' );

        if ($path === '') {
            return '';
        }

        $schemeEnd = \strpos($path, '://');

        if ($schemeEnd !== false && \is_path_scheme(\substr($path, 0, $schemeEnd))) {
            $path = \substr($path, 0, $schemeEnd + 3) . \strtr(\substr($path, $schemeEnd + 3), '\\', \DIR_SEP);
        } else {
            $path = \strtr($path, '\\', \DIR_SEP);
        }

        // DIR_SEP is `/` in this package; still map any remaining `/` when a
        // consumer redefines the constant.
        if (\DIR_SEP !== '/') {
            $path = \str_replace('/', \DIR_SEP, $path);
        }

        return self::trailingSeparator($path, $trailingSeparator);
    }

    /**
     * Whether `$path` is absolute on the shapes this package recognizes.
     *
     * Absolute:
     * - POSIX `/…`
     * - UNC `//…`
     * - Windows drive with separator `X:/…`, or bare `X:`
     * - Stream wrapper / URI scheme `scheme://…`
     *
     * Not absolute: bare relatives, `./…`, and drive-relative `X:foo` (no separator).
     *
     * Separators are normalized before the check. Length is asserted via
     * {@see Assert::validPathLength()}.
     *
     * @param string $path
     *
     * @return bool
     *
     * @throws RuntimeException When `$path` exceeds {@see \MAX_PATH_LENGTH}
     *
     * @phpstan-assert-if-true non-empty-string $path
     */
    public static function isAbsolutePath(
        string $path,
    ): bool {
        if ($path === '') {
            return false;
        }

        Assert::validPathLength($path);

        $path = \strtr($path, '\\', \DIR_SEP);

        [$prefix, $body] = self::pathRoot($path);

        if ($prefix === '' || $prefix === './') {
            return false;
        }

        // `C:foo` is drive-relative; bare `C:` is treated as absolute.
        if (\strlen($prefix) === 2 && $prefix[1] === ':') {
            return $body === '';
        }

        return true;
    }

    /**
     * Cosmetic URL / URL-path string normalizer.
     *
     * This is **not** an RFC 3986 validator — use {@see Assert::validUrl()} /
     * {@see Assert::validUri()} (or {@see Url} / {@see Uri}) for shape checks.
     * It rewrites separators, optionally substitutes whitespace,
     * lowercases the scheme, collapses duplicate `/` in the path, and reattaches
     * query / fragment. Path case is preserved unless `$lowercasePath` is set.
     *
     * When no scheme is present, the result is rooted with `/` (root-relative form).
     *
     * ```
     * Normalize::url('HTTPS://Example.COM/a//b?x=1#f');
     * // => 'https://Example.COM/a/b?x=1#f'
     * ```
     *
     * @param null|array<null|string|Stringable>|string|Stringable $url
     * @param false|string                                         $substituteWhitespace Replace runs of whitespace (`'-'` default; `false` keeps them)
     * @param bool                                                 $trailingSlash        `true` ensure trailing `/` on the path; `false` strip it
     * @param bool                                                 $lowercasePath        Lowercase the path body (scheme always lowercased)
     */
    public static function url(
        null|string|Stringable|array $url,
        false|string                 $substituteWhitespace = '-',
        bool                         $trailingSlash = false,
        bool                         $lowercasePath = false,
    ): string {
        if ($url === null || $url === '' || $url === []) {
            return '';
        }

        if (\is_array($url)) {
            $parts = [];

            foreach ($url as $part) {
                if ($part === null) {
                    continue;
                }

                $part = (string) $part;

                if ($part !== '') {
                    $parts[] = $part;
                }
            }

            $url = \implode('/', $parts);
        } else {
            $url = (string) $url;
        }

        if ($url === '') {
            return '';
        }

        $url = \strtr($url, '\\', '/');

        if ($substituteWhitespace !== false) {
            $url = (string) \preg_replace('#\s+#', $substituteWhitespace, $url);
        }

        $scheme   = '';
        $query    = '';
        $fragment = '';

        $schemeEnd = \strpos($url, '://');

        if ($schemeEnd !== false && \is_path_scheme(\substr($url, 0, $schemeEnd))) {
            $scheme = \strtolower(\substr($url, 0, $schemeEnd)) . '://';
            $url    = \substr($url, $schemeEnd + 3);
        }

        $queryPos    = \strpos($url, '?');
        $fragmentPos = \strpos($url, '#');

        if ($queryPos !== false && $fragmentPos !== false) {
            if ($queryPos < $fragmentPos) {
                [$url, $rest] = \explode('?', $url, 2);
                [$query, $fragment] = \explode('#', $rest, 2);
                $query    = '?' . $query;
                $fragment = '#' . $fragment;
            } else {
                [$url, $rest] = \explode('#', $url, 2);
                [$fragment, $query] = \explode('?', $rest, 2);
                $fragment = '#' . $fragment;
                $query    = '?' . $query;
            }
        } elseif ($queryPos !== false) {
            [$url, $query] = \explode('?', $url, 2);
            $query = '?' . $query;
        } elseif ($fragmentPos !== false) {
            [$url, $fragment] = \explode('#', $url, 2);
            $fragment = '#' . $fragment;
        }

        $segments = \array_filter(
            \explode('/', $url),
            static fn(string $segment): bool => $segment !== '',
        );

        $path = \implode('/', $segments);

        if ($lowercasePath) {
            $path = \strtolower($path);
        }

        $path = self::trailingSeparator($path, $trailingSlash, '/');

        if ($trailingSlash && $path === '') {
            $path = '/';
        }

        // No scheme → root-relative (`/path`). Avoid `//` when the path itself is `/`.
        if ($scheme === '') {
            if ($path === '' || $path === '/') {
                return '/' . $query . $fragment;
            }

            return '/' . $path . $query . $fragment;
        }

        return $scheme . $path . $query . $fragment;
    }

    /**
     * Splits a slash-normalized path into a preserved root prefix and a relative body.
     *
     * Recognised roots (checked in order):
     * - Stream wrapper: `scheme://` (body may itself carry a nested root)
     * - UNC / network: `//`
     * - Absolute: `/`
     * - Dot-relative: `./`
     * - Windows drive: `X:` or `X:/`
     *
     * @return array{0: string, 1: string} `[prefix, body]`
     */
    private static function pathRoot(
        string $path,
    ): array {
        $schemeEnd = \strpos($path, '://');

        if ($schemeEnd !== false) {
            $scheme = \substr($path, 0, $schemeEnd);

            // Leading ALPHA, then scheme charset — same bar as URI helpers.
            if ($scheme !== '' && \strspn($scheme[0], \CHARSET_ALPHA) === 1 && \strspn($scheme, \CHARSET_URI_SCHEME) === \strlen($scheme)) {
                $wrapper = \substr($path, 0, $schemeEnd + 3);
                [$nested, $body] = self::pathRoot(\substr($path, $schemeEnd + 3));

                return [$wrapper . $nested, $body];
            }
        }

        if (\str_starts_with($path, '//')) {
            return ['//', \substr($path, 2)];
        }

        if ($path !== '' && $path[0] === \DIR_SEP) {
            return [\DIR_SEP, \substr($path, 1)];
        }

        // Bare `.` is current-dir — same root as `./` (otherwise the `.` segment
        // is dropped and the path collapses to empty).
        if ($path === '.' || \str_starts_with($path, './')) {
            return ['./', $path === '.' ? '' : \substr($path, 2)];
        }

        // Windows drive: `C:`, `C:/…` (letter case preserved).
        if (\strlen($path) >= 2 && \strspn($path[0], \CHARSET_ALPHA) === 1 && $path[1] === ':') {
            if (\strlen($path) >= 3 && $path[2] === \DIR_SEP) {
                return [\substr($path, 0, 3), \substr($path, 3)];
            }

            return [\substr($path, 0, 2), \substr($path, 2)];
        }

        return ['', $path];
    }

    /**
     * Enforce presence or absence of a trailing separator.
     *
     * - `$trailing = true`  → append `$separator` when missing
     * - `$trailing = false` → strip trailing `$separator`s
     *
     * All-separator roots are preserved: `/` stays `/`, `//` stays `//`.
     */
    private static function trailingSeparator(
        string $path,
        bool   $trailing,
        string $separator = \DIR_SEP,
    ): string {
        if ($path === '') {
            return '';
        }

        if ($trailing) {
            return \str_ends_with($path, $separator) ? $path : $path . $separator;
        }

        return \rtrim($path, $separator) ?: ( \str_starts_with($path, '//') ? '//' : $separator );
    }
}

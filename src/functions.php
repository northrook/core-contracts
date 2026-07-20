<?php

declare(strict_types=1);

namespace {
    /**
     * Whether the current SAPI is CLI or phpdbg.
     */
    function is_cli(): bool
    {
        return \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
    }

    /**
     * Whether the current SAPI is PHP's built-in development server.
     */
    function is_cli_server(): bool
    {
        return \PHP_SAPI === 'cli-server';
    }

    /**
     * Whether the current SAPI is CGI or CGI-FastCGI.
     */
    function is_cgi(): bool
    {
        return \PHP_SAPI === 'cgi' || \PHP_SAPI === 'cgi-fcgi';
    }

    /**
     * Whether the current SAPI is PHP-FPM.
     */
    function is_fpm(): bool
    {
        return \PHP_SAPI === 'fpm-fcgi';
    }

    /**
     * Whether the current SAPI is HTTP-facing (not CLI/phpdbg).
     */
    function is_web(): bool
    {
        return ! is_cli();
    }

    /**
     * Whether the current SAPI matches `$sapi` exactly.
     */
    function is_sapi(
        string $sapi,
    ): bool {
        return \PHP_SAPI === $sapi;
    }

    /**
     * Whether PHPUnit is the active test runner (composer install or phar).
     */
    function is_phpunit(): bool
    {
        return \defined('PHPUNIT_COMPOSER_INSTALL') || \defined('__PHPUNIT_PHAR__');
    }

    /**
     * Whether Pest is present in the process.
     */
    function is_pest(): bool
    {
        return \defined('PEST') || \class_exists('\Pest\Tester', false) || \class_exists('\Pest\TestSuite', false);
    }

    /**
     * Whether Codeception is present in the process.
     */
    function is_codeception(): bool
    {
        return \defined('CODECEPTION_VERSION') || \class_exists('\Codeception\Codecept', false);
    }

    /**
     * Whether any known test runner is active or present.
     */
    function is_test_runner(): bool
    {
        return is_phpunit() || is_pest() || is_codeception();
    }

    /**
     * Whether the Zend OPcache extension is loaded.
     */
    function is_opcache_loaded(): bool
    {
        return \extension_loaded('Zend OPcache') || \extension_loaded('opcache');
    }

    /**
     * Whether OPcache is enabled for the current SAPI.
     */
    function is_opcache_enabled(): bool
    {
        if (! is_opcache_loaded()) {
            return false;
        }

        if (\function_exists('opcache_get_status')) {
            $status = @\opcache_get_status(false);

            if (\is_array($status) && \array_key_exists('opcache_enabled', $status)) {
                return (bool) $status['opcache_enabled'];
            }
        }

        $ini = is_cli() ? 'opcache.enable_cli' : 'opcache.enable';

        return \filter_var(\ini_get($ini), \FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether OPcache JIT is active for the current process.
     */
    function is_opcache_jit_enabled(): bool
    {
        if (! is_opcache_enabled()) {
            return false;
        }

        if (\function_exists('opcache_get_status')) {
            $status = @\opcache_get_status(false);

            if (\is_array($status) && isset($status['jit']) && \is_array($status['jit'])) {
                if (\array_key_exists('enabled', $status['jit'])) {
                    return (bool) $status['jit']['enabled'];
                }

                if (\array_key_exists('on', $status['jit'])) {
                    return (bool) $status['jit']['on'];
                }
            }
        }

        $jit = (string) \ini_get('opcache.jit');

        if ($jit === '' || $jit === '0' || \strtolower($jit) === 'disable') {
            return false;
        }

        $buffer = (string) \ini_get('opcache.jit_buffer_size');

        return $buffer !== '' && $buffer !== '0';
    }

    /**
     * Whether the Xdebug extension is loaded.
     */
    function is_xdebug_loaded(): bool
    {
        return \extension_loaded('xdebug');
    }

    /**
     * Whether Xdebug is loaded and not effectively off.
     */
    function is_xdebug_enabled(): bool
    {
        if (! is_xdebug_loaded()) {
            return false;
        }

        if (\function_exists('xdebug_is_debugger_active') && \xdebug_is_debugger_active()) {
            return true;
        }

        if (\function_exists('xdebug_info')) {
            /**
             * @var mixed $modes
             *
             * @noinspection PhpVoidFunctionResultUsedInspection
             */
            $modes = @\xdebug_info('mode');

            if (\is_array($modes)) {
                return $modes !== [] && ! ( \count($modes) === 1 && ( $modes[0] ?? null ) === 'off' );
            }
        }

        $mode = (string) \ini_get('xdebug.mode');

        if ($mode === '' || \strtolower($mode) === 'off') {
            return false;
        }

        return true;
    }

    /**
     * Whether the PCOV coverage extension is loaded.
     */
    function is_pcov_loaded(): bool
    {
        return \extension_loaded('pcov');
    }

    /**
     * Whether Tracy Debugger is available.
     */
    function is_tracy_loaded(): bool
    {
        return \class_exists('\Tracy\Debugger', false);
    }

    /**
     * Whether coverage/debug tooling is active (Xdebug enabled or PCOV loaded).
     */
    function is_debug_probe_active(): bool
    {
        return is_xdebug_enabled() || is_pcov_loaded();
    }

    /**
     * Whether the host OS family is Windows.
     */
    function is_windows(): bool
    {
        return \PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Whether the host OS family is Linux.
     */
    function is_linux(): bool
    {
        return \PHP_OS_FAMILY === 'Linux';
    }

    /**
     * Whether the host OS family is Darwin (macOS).
     */
    function is_macos(): bool
    {
        return \PHP_OS_FAMILY === 'Darwin';
    }

    /**
     * Whether the host OS family is BSD.
     */
    function is_bsd(): bool
    {
        return \PHP_OS_FAMILY === 'BSD';
    }

    /**
     * Whether the host OS family is Solaris.
     */
    function is_solaris(): bool
    {
        return \PHP_OS_FAMILY === 'Solaris';
    }

    /**
     * Whether the host OS is a Unix-like family (not Windows).
     */
    function is_unix(): bool
    {
        return ! is_windows();
    }

    /**
     * Whether the process appears to be running under WSL.
     */
    function is_wsl(): bool
    {
        if (! is_linux()) {
            return false;
        }

        $path = '/proc/version';

        if (! \is_readable($path)) {
            return false;
        }

        $version = @\file_get_contents($path);

        if ($version === false) {
            return false;
        }

        return \str_contains(\strtolower($version), 'microsoft') || \str_contains(\strtolower($version), 'wsl');
    }

    /**
     * Whether this PHP build uses 64-bit integers.
     */
    function is_64bit(): bool
    {
        return \PHP_INT_SIZE === 8;
    }

    /**
     * Whether this PHP build uses 32-bit integers.
     */
    function is_32bit(): bool
    {
        return \PHP_INT_SIZE === 4;
    }

    /**
     * Whether this PHP build is Zend thread-safe (ZTS).
     */
    function is_thread_safe(): bool
    {
        return \defined('ZEND_THREAD_SAFE') && \ZEND_THREAD_SAFE;
    }

    /**
     * Whether this PHP binary was compiled as a debug build.
     */
    function is_php_debug_build(): bool
    {
        return \defined('PHP_DEBUG') && \PHP_DEBUG;
    }

    /**
     * Whether the current script is running inside a Phar archive.
     */
    function is_phar(): bool
    {
        return \class_exists(\Phar::class, false) && \Phar::running() !== '';
    }

    /**
     * Whether the STDIN constant is defined.
     */
    function has_stdin(): bool
    {
        return \defined('STDIN');
    }

    /**
     * Whether STDIN exists and is an interactive TTY.
     */
    function is_interactive(): bool
    {
        if (! has_stdin()) {
            return false;
        }

        /** @var resource $stdin */
        $stdin = \STDIN;

        if (\function_exists('stream_isatty')) {
            return \stream_isatty($stdin);
        }

        if (\function_exists('posix_isatty')) {
            return \posix_isatty($stdin);
        }

        return false;
    }

    /**
     * Whether the process effective user is root (posix only).
     */
    function is_root(): bool
    {
        if (! \function_exists('posix_geteuid')) {
            return false;
        }

        return \posix_geteuid() === 0;
    }

    /**
     * Whether Composer reports a development install (`COMPOSER_DEV_MODE`).
     */
    function is_composer_dev(): bool
    {
        if (\defined('COMPOSER_DEV_MODE')) {
            return \filter_var(\COMPOSER_DEV_MODE, \FILTER_VALIDATE_BOOLEAN);
        }

        $env = $_ENV['COMPOSER_DEV_MODE'] ?? \getenv('COMPOSER_DEV_MODE');

        if ($env === false || $env === '') {
            return false;
        }

        return \filter_var($env, \FILTER_VALIDATE_BOOLEAN);
    }
}

namespace Northrook\Contracts\Internal {
    use Northrook\Contracts\Exceptions\RuntimeException;

    /**
     * Tests whether every character in a string belongs to a fixed character set.
     *
     * This is the low-level primitive behind the public `str_is_*` validators.
     *
     * Matching is literal byte-for-byte against `$characters`.
     *
     * There is no locale, Unicode property, or normalization step.
     *
     * @internal
     *
     * @param string $string     Candidate value to inspect
     * @param string $characters Allowed code units (must be non-empty)
     *
     * @return bool `true` when `$string` is a non-empty sequence of valid bytes, otherwise `false`
     *
     * @throws RuntimeException when `$characters` is empty
     */
    function _match_charset(
        string $string,
        string $characters,
    ): bool {
        if ($string === '') {
            return false;
        }

        if ($characters === '') {
            throw new RuntimeException(
                message: 'The characters string cannot be empty.',
                context: \func_get_args(),
            );
        }

        return \strspn($string, $characters) === \strlen($string);
    }

    /**
     * Validates a URI component: each byte is in `$charset`, or a `%` HEXDIG HEXDIG sequence.
     *
     * Empty `$string` is valid (callers decide emptiness separately when needed).
     *
     * @internal
     */
    function _uri_match_component(
        string $string,
        string $charset,
    ): bool {
        $length = \strlen($string);
        $offset = 0;

        while ($offset < $length) {
            $byte = $string[$offset];

            if ($byte === '%') {
                if (( $offset + 2 ) >= $length) {
                    return false;
                }

                if (\strspn($string, \CHARSET_XDIGIT, $offset + 1, 2) !== 2) {
                    return false;
                }

                $offset += 3;
                continue;
            }

            if (! \str_contains($charset, $byte)) {
                return false;
            }

            $offset++;
        }

        return true;
    }

    /**
     * Whether `$scheme` matches RFC 3986 `scheme` production (length unchecked).
     *
     * @internal
     */
    function _uri_is_scheme(
        string $scheme,
    ): bool {
        if ($scheme === '' || \strspn($scheme[0], \CHARSET_ALPHA) !== 1) {
            return false;
        }

        return \strspn($scheme, \CHARSET_URI_SCHEME) === \strlen($scheme);
    }

    /**
     * Scans a URI or URI-reference string (RFC 3986 shape, ASCII / pct-encoded only).
     *
     * @internal
     *
     * @return null|array{authority: bool, host: string}
     */
    function _scan_uri(
        string $value,
        bool $allowRelative = false,
        bool $allowSingleCharScheme = false,
    ): null|array {
        if ($value === '') {
            return null;
        }

        $schemeEnd = \strpos($value, ':');

        if ($schemeEnd !== false && $schemeEnd > 0) {
            $scheme = \substr($value, 0, $schemeEnd);

            if (_uri_is_scheme($scheme)) {
                if (! $allowSingleCharScheme && $schemeEnd < 2) {
                    return null;
                }

                return _scan_uri_tail(\substr($value, $schemeEnd + 1));
            }
        }

        if (! $allowRelative) {
            return null;
        }

        return _scan_relative_ref($value);
    }

    /**
     * Scans hier-part / opaque tail after `scheme:`, or a relative-ref body.
     *
     * @internal
     *
     * @return null|array{authority: bool, host: string}
     */
    function _scan_uri_tail(
        string $rest,
    ): null|array {
        $authority = false;
        $host      = '';

        if (\str_starts_with($rest, '//')) {
            $parsed = _uri_parse_authority(\substr($rest, 2));

            if ($parsed === null) {
                return null;
            }

            $authority = true;
            $host      = $parsed['host'];
            $rest      = $parsed['rest'];
        }

        $rest = _uri_strip_query_fragment($rest);

        if ($rest === null) {
            return null;
        }

        if ($authority) {
            if ($rest !== '' && ! _uri_match_path_abempty($rest)) {
                return null;
            }
        } elseif ($rest === '') {
            // path-empty
        } elseif ($rest[0] === '/') {
            if (! _uri_match_path_absolute($rest)) {
                return null;
            }
        } elseif (! _uri_match_path_rootless($rest)) {
            return null;
        }

        return [
            'authority' => $authority,
            'host'      => $host,
        ];
    }

    /**
     * Scans RFC 3986 `relative-ref`.
     *
     * @internal
     *
     * @return null|array{authority: bool, host: string}
     */
    function _scan_relative_ref(
        string $value,
    ): null|array {
        $authority = false;
        $host      = '';
        $rest      = $value;

        if (\str_starts_with($rest, '//')) {
            $parsed = _uri_parse_authority(\substr($rest, 2));

            if ($parsed === null) {
                return null;
            }

            $authority = true;
            $host      = $parsed['host'];
            $rest      = $parsed['rest'];
        }

        $rest = _uri_strip_query_fragment($rest);

        if ($rest === null) {
            return null;
        }

        if ($authority) {
            if ($rest !== '' && ! _uri_match_path_abempty($rest)) {
                return null;
            }
        } elseif ($rest === '') {
            // path-empty (e.g. `?q=1` / `#f`)
        } elseif ($rest[0] === '/') {
            if (! _uri_match_path_absolute($rest)) {
                return null;
            }
        } elseif (! _uri_match_path_noscheme($rest)) {
            return null;
        }

        return [
            'authority' => $authority,
            'host'      => $host,
        ];
    }

    /**
     * @internal
     *
     * @return null|array{host: string, rest: string}
     */
    function _uri_parse_authority(
        string $rest,
    ): null|array {
        $authorityEnd  = \strcspn($rest, '/?#');
        $authorityPart = \substr($rest, 0, $authorityEnd);
        $rest          = \substr($rest, $authorityEnd);
        $host          = '';

        $userinfoEnd = \strrpos($authorityPart, '@');

        if ($userinfoEnd !== false) {
            $userinfo = \substr($authorityPart, 0, $userinfoEnd);
            $hostPort = \substr($authorityPart, $userinfoEnd + 1);

            if (! _uri_match_component($userinfo, \CHARSET_URI_USERINFO)) {
                return null;
            }
        } else {
            $hostPort = $authorityPart;
        }

        if ($hostPort !== '' && $hostPort[0] === '[') {
            $close = \strpos($hostPort, ']');

            if ($close === false) {
                return null;
            }

            $host     = \substr($hostPort, 0, $close + 1);
            $portPart = \substr($hostPort, $close + 1);
            $inner    = \substr($host, 1, -1);

            if ($inner === '') {
                return null;
            }

            if ($inner[0] === 'v' || $inner[0] === 'V') {
                if (
                    \strlen($inner) < 3
                    || ! _uri_match_component(
                        \substr($inner, 1),
                        \CHARSET_XDIGIT . '.' . \CHARSET_URI_UNRESERVED . \CHARSET_URI_SUBDELIMS . ':',
                    )
                ) {
                    return null;
                }
            } elseif (\strspn($inner, \CHARSET_URI_IP_LITERAL) !== \strlen($inner)) {
                return null;
            }

            if ($portPart !== '') {
                if ($portPart[0] !== ':') {
                    return null;
                }

                if (
                    \strlen($portPart) > 1
                    && \strspn($portPart, \CHARSET_DIGIT, 1) !== ( \strlen($portPart) - 1 )
                ) {
                    return null;
                }
            }
        } else {
            $portSep = \strpos($hostPort, ':');

            if ($portSep === false) {
                $host = $hostPort;
            } else {
                $host = \substr($hostPort, 0, $portSep);
                $port = \substr($hostPort, $portSep + 1);

                if ($port !== '' && \strspn($port, \CHARSET_DIGIT) !== \strlen($port)) {
                    return null;
                }
            }

            if (! _uri_match_component($host, \CHARSET_URI_REGNAME)) {
                return null;
            }
        }

        return [
            'host' => $host,
            'rest' => $rest,
        ];
    }

    /**
     * Validates and strips query / fragment; returns the path remainder or null.
     *
     * @internal
     */
    function _uri_strip_query_fragment(
        string $rest,
    ): null|string {
        $fragmentSep = \strpos($rest, '#');

        if ($fragmentSep !== false) {
            $fragment = \substr($rest, $fragmentSep + 1);
            $rest     = \substr($rest, 0, $fragmentSep);

            if (! _uri_match_component($fragment, \CHARSET_URI_QUERY)) {
                return null;
            }
        }

        $querySep = \strpos($rest, '?');

        if ($querySep !== false) {
            $query = \substr($rest, $querySep + 1);
            $rest  = \substr($rest, 0, $querySep);

            if (! _uri_match_component($query, \CHARSET_URI_QUERY)) {
                return null;
            }
        }

        return $rest;
    }

    /**
     * @internal
     */
    function _uri_match_path_abempty(
        string $path,
    ): bool {
        if ($path === '') {
            return true;
        }

        if ($path[0] !== '/') {
            return false;
        }

        return array_all(
            \explode('/', \substr($path, 1)),
            fn($segment) => _uri_match_component($segment, \CHARSET_URI_PCHAR),
        );
    }

    /**
     * @internal
     */
    function _uri_match_path_absolute(
        string $path,
    ): bool {
        if ($path === '/') {
            return true;
        }

        if ($path[0] !== '/' || \strlen($path) < 2) {
            return false;
        }

        return _uri_match_path_rootless(\substr($path, 1));
    }

    /**
     * @internal
     */
    function _uri_match_path_rootless(
        string $path,
    ): bool {
        if ($path === '') {
            return false;
        }

        $segments = \explode('/', $path);

        // segment-nz *( "/" segment ) — first segment must be non-empty
        if ($segments[0] === '') {
            return false;
        }

        return array_all(
            $segments,
            static fn($segment) => _uri_match_component($segment, \CHARSET_URI_PCHAR),
        );
    }

    /**
     * RFC 3986 `path-noscheme` = segment-nz-nc *( "/" segment ).
     *
     * @internal
     */
    function _uri_match_path_noscheme(
        string $path,
    ): bool {
        if ($path === '') {
            return false;
        }

        $segments = \explode('/', $path);

        if ($segments[0] === ''
            || ! _uri_match_component($segments[0], \CHARSET_URI_SEGMENT_NC)
        ) {
            return false;
        }

        $rest = \array_slice($segments, 1);

        return $rest === [] || array_all(
            $rest,
            static fn($segment) => _uri_match_component($segment, \CHARSET_URI_PCHAR),
        );
    }
}

namespace Northrook\Contracts {
    use Northrook\Contracts\Exceptions\FilesystemException;
    use Northrook\Contracts\Exceptions\RuntimeException;

    use function Northrook\Contracts\Internal\_match_charset;
    use function Northrook\Contracts\Internal\_scan_uri;

    /**
     * Generates a 16-character non-cryptographic Crockford Base32 string.
     *
     * Uses `mt_rand` as its entropy source.
     *
     * Suitable for temp file names and other low-stakes identifiers.
     *
     * Not appropriate for security-sensitive contexts.
     *
     * @return non-empty-string 16 characters from {@see \CROCKFORD_BASE32}
     */
    function get_hash(): string
    {
        $output = \array_fill(0, 16, '');
        $bits   = 0;
        $val    = 0;

        for ($i = 0; $i < 16; $i++) {
            if ($bits < 5) {
                $val  = \mt_rand(0, 0xFFFF) | ( \mt_rand(0, 0xFFFF) << 16 );
                $bits = 32;
            }

            $output[$i] = \CROCKFORD_BASE32[( $val >> ( $bits - 5 ) ) & 31];
            $bits       -= 5;
        }

        $hash = \implode('', $output);

        if (strlen($hash) !== 16) {
            throw new RuntimeException(
                message: 'Unexpected hash length: ' . \strlen($hash) . '. Expected 16.',
                context: \func_get_args(),
            );
        }

        return $hash;
    }

    /**
     * Canonical xxHash32 checksum of a value as Crockford Base32.
     *
     * Returns an 8-character string; the standard short checksum shape for
     * cache keys, path namespaces, and other non-cryptographic fingerprints.
     *
     * Compatible with `Northrook\Hash::checksum()` from `northrook/hasher`.
     *
     * Not appropriate for security-sensitive contexts.
     *
     * @return non-empty-string 8 characters from {@see \CROCKFORD_BASE32}
     */
    function get_checksum(
        string $value,
    ): string {
        $output = \array_fill(0, 8, '');
        $packed = \unpack('N', \hash('xxh32', $value, true));
        $digest = $packed[1] ?? throw new RuntimeException(
            message: 'Failed to unpack xxh32 digest',
            context: ['value' => $value],
        );

        // LSB-first into the rightmost characters (same as Hash::value / checksum)
        for ($i = 7; $i >= 0; $i--) {
            $output[$i] = \CROCKFORD_BASE32[$digest & 31];
            $digest     >>= 5;
        }

        $checksum = \implode('', $output);

        if (strlen($checksum) !== 8) {
            throw new RuntimeException(
                message: 'Unexpected checksum length: ' . \strlen($checksum) . '. Expected 8.',
                context: \func_get_args(),
            );
        }

        return $checksum;
    }

    /**
     * Builds a unique path under the system temporary directory.
     *
     * Does not create directories or files; returns a path string only.
     *
     * - Root is always {@see \sys_get_temp_dir()}
     * - `$relativePath` defaults to `tmp` when `null` or empty; trailing `!` characters are stripped
     * - Nested segments are allowed (e.g. `namespace/cache`)
     * - Separators are normalized to {@see \DIR_SEP}; empty and `.` segments are dropped
     * - A `!hash` suffix is appended for uniqueness, using {@see get_hash()}
     *
     * @param null|string $relativePath Basename or relative path under the temp root
     *
     * @return non-empty-string Absolute (or drive-rooted) path ending in `!` + 16 Crockford chars
     *
     * @throws RuntimeException if the path attempts upwards traversal
     */
    function get_temp_path(
        null|string $relativePath = null,
    ): string {
        $relativePath = $relativePath === null || $relativePath === '' ? 'tmp' : \rtrim($relativePath, '!');
        $absolutePath = \sys_get_temp_dir() . \DIR_SEP . $relativePath;

        $normalizedPath = \strtr($absolutePath, '\\', \DIR_SEP);
        $rootSeparator  = \str_starts_with($normalizedPath, \DIR_SEP) ? \DIR_SEP : '';

        $fragments = \array_filter(
            \explode(\DIR_SEP, $normalizedPath),
            static fn(string $f): bool => $f !== '' && $f !== '.',
        );

        if (\in_array('..', $fragments, true)) {
            throw new RuntimeException(
                message: "Invalid path: `{$normalizedPath}`. Cannot traverse upwards.",
                context: \func_get_args(),
            );
        }

        return $rootSeparator . \implode(\DIR_SEP, $fragments) . '!' . get_hash();
    }

    /**
     * Validates structural string keys used across the contract layer.
     *
     * - Integer `$key` values are rejected; only strings are inspected.
     * - String keys must be non-empty and within `$min` and `$max` byte length.
     * - Every byte must belong to `$charset`, plus `$separator` when segment-mode is active.
     * - The first byte must not be an ASCII digit (`0-9`).
     * - When `$separator` is empty, the key is a single token (no segment rules).
     * - When `$separator` is non-empty: it must be exactly one byte and not appear in `$charset`;
     *   the key must not start or end with the separator; consecutive separators are rejected.
     * - A configured separator is not required to appear in the key (e.g. `pathroot` is valid with `.`).
     * - Matching is literal byte-for-byte; Unicode and normalization are not applied.
     *
     * Typical `$charset` / `$separator` pairings:
     * - Path-style keys: `CHARSET_ALPHA` with `-_/` and `.`
     * - Container keys: `CHARSET_ALNUM` with `-_\/` and `.`
     *
     * @param int|string $key       Candidate key
     * @param int        $min       Minimum inclusive byte length (default `1`).
     * @param int        $max       Maximum inclusive byte length (default `MAX_PATH_LENGTH`).
     * @param string     $separator Single-character segment delimiter. An empty string
     *                                    disables segment rules and treats the key as a single token.
     * @param string     $charset   Allowed bytes for segment bodies (and the whole key when
     *                                    no separator is configured).
     *
     * @return bool `true` when `$key` satisfies every rule above; `false` otherwise.
     *
     * @throws RuntimeException When `$min`/`$max` are out of range, `$charset` is empty,
     *                                   or `$separator` is invalid (not exactly one character, or
     *                                   present in `$charset`).
     *
     * @phpstan-assert-if-true non-empty-string $key
     *
     * @see \MAX_PATH_LENGTH
     * @see \CHARSET_ALNUM
     * @see \CHARSET_ALPHA
     */
    function is_valid_key(
        int|string $key,
        int $min = 1,
        int $max = MAX_PATH_LENGTH,
        string $separator = '',
        string $charset = \CHARSET_ALNUM,
    ): bool {
        if ($min > $max || $min < 1 || $max > MAX_PATH_LENGTH) {
            $limit = MAX_PATH_LENGTH;
            throw new RuntimeException(
                message: "Invalid property key length: {$min} to {$max}. Must be between 1 and {$limit}.",
                context: \func_get_args(),
            );
        }

        if ($charset === '') {
            throw new RuntimeException(
                message: 'The charset cannot be empty.',
                context: \func_get_args(),
            );
        }

        if (\is_int($key)) {
            return false;
        }

        $allowed = $charset;

        if ($separator !== '') {
            if (\strlen($separator) !== 1) {
                throw new RuntimeException(
                    message: "Invalid separator: `{$separator}`. Must be exactly one character.",
                    context: \func_get_args(),
                );
            }

            if (\str_contains($charset, $separator)) {
                throw new RuntimeException(
                    message: "Invalid separator: `{$separator}`. Must not appear in `{$charset}`.",
                    context: \func_get_args(),
                );
            }

            if (\str_contains(
                $key,
                $separator . $separator,
            )) {
                return false;
            }

            if (\str_starts_with(
                $key,
                $separator,
            )) {
                return false;
            }

            if (\str_ends_with(
                $key,
                $separator,
            )) {
                return false;
            }

            $allowed .= $separator;
        }

        if (\strlen($key) < $min || \strlen($key) > $max) {
            return false;
        }

        if ($key[0] >= '0' && $key[0] <= '9') {
            return false;
        }

        return _match_charset(
            $key,
            $allowed,
        );
    }

    /**
     * Validates the length of a path string.
     *
     * @param string            $path       path to validate
     * @param bool              $assertive  whether to throw an exception when `$path` exceeds `$max` bytes (default `true`)
     * @param non-negative-int  $maxLength  maximum byte length (default `MAX_PATH_LENGTH`)
     *
     * @return bool
     * @phpstan-assert-if-true non-empty-string $path
     *
     * @throws FilesystemException when `$assertive` is `true` and `$path` exceeds `$max` bytes
     */
    function is_valid_path_length(
        string $path,
        bool $assertive = true,
        int $maxLength = MAX_PATH_LENGTH,
    ): bool {
        if ($maxLength <= 0) {
            throw new RuntimeException(
                message: "Invalid max length: `{$maxLength}`. Must be greater than zero.",
                context: \func_get_args(),
            );
        }

        if (\strlen($path) <= $maxLength) {
            return true;
        }

        return $assertive
            ? throw new FilesystemException(
                message: "Path `{$path}` exceeds maximum byte length of `{$maxLength}`",
                path: $path,
                context: \func_get_args(),
            )
            : false;
    }

    /**
     * Tests whether a string contains only 7-bit ASCII code units.
     *
     * - Allowed bytes are exactly those in {@see \CHARSET_ASCII} (ordinals `0x00`–`0x7F`).
     * - Bytes with the high bit set (ordinal `>= 0x80`) are rejected.
     * - An empty string is accepted.
     * - This is a raw encoding check, not Unicode normalization.
     *
     * @param string $string Candidate value to inspect
     *
     * @return bool `true` when every byte is ASCII or `$string` is empty, otherwise `false`
     */
    function str_is_ascii(
        string $string,
    ): bool {
        return $string === ''
        || _match_charset(
            $string,
            \CHARSET_ASCII,
        );
    }

    /**
     * Tests whether a string is a non-empty sequence of ASCII letters.
     *
     * - Allowed bytes are exactly those in {@see \CHARSET_ALPHA} (`a-z`, `A-Z`).
     * - Digits, punctuation, whitespace, and non-ASCII bytes are rejected.
     * - An empty string is rejected.
     *
     * @param string $string Candidate value to inspect
     *
     * @return bool `true` when `$string` is non-empty and every byte is a letter
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    function str_is_alpha(
        string $string,
    ): bool {
        return _match_charset(
            $string,
            \CHARSET_ALPHA,
        );
    }

    /**
     * Tests whether a string is a non-empty sequence of ASCII letters and digits.
     *
     * - Allowed bytes are exactly those in {@see \CHARSET_ALNUM} (`0-9`, `a-z`, `A-Z`).
     * - Punctuation, whitespace, and non-ASCII bytes are rejected.
     * - An empty string is rejected.
     *
     * @param string $string Candidate value to inspect.
     *
     * @return bool `true` when `$string` is non-empty and every byte is alphanumeric
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    function str_is_alnum(
        string $string,
    ): bool {
        return _match_charset(
            $string,
            \CHARSET_ALNUM,
        );
    }

    /**
     * Tests whether a string is a non-empty sequence of ASCII decimal digits.
     *
     *  - Allowed bytes are exactly those in {@see \CHARSET_DIGIT} (`0-9`).
     *  - Letters, punctuation, and non-ASCII bytes are rejected.
     *  - An empty string is rejected.
     *
     * @param string $string Candidate value to inspect.
     *
     * @return bool `true` when `$string` is non-empty and every byte is a digit
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    function str_is_digit(
        string $string,
    ): bool {
        return _match_charset(
            $string,
            \CHARSET_DIGIT,
        );
    }

    /**
     * Tests whether a string is a non-empty sequence of ASCII hexadecimal digits.
     *
     * - Allowed bytes are exactly those in {@see \CHARSET_XDIGIT} (`0-9`, `a-f`, `A-F`).
     * - Other letters, punctuation, and non-ASCII bytes are rejected.
     * - An empty string is rejected.
     *
     * @param string $string Candidate value to inspect.
     *
     * @return bool `true` when `$string` is non-empty and every byte is a hex digit
     *
     * @phpstan-assert-if-true non-empty-string $string
     */
    function str_is_xdigit(
        string $string,
    ): bool {
        return _match_charset(
            $string,
            \CHARSET_XDIGIT,
        );
    }

    /**
     * Validates a cache item key.
     *
     * - Allowed bytes: {@see \CHARSET_ALNUM} and `.-:` (`0-9`, `a-z`, `A-Z`, `.-:`).
     * - Unicode, whitespace, and other punctuation are rejected.
     * - An empty string is rejected.
     *
     * The colon is typically used to separate a logical name from a version or variant suffix (e.g. `app.cache.item:v2`).
     *
     * This keeps keys safe for filesystem paths, PSR-6 backends, and dot-notation lookups without additional escaping.
     *
     * @param string $key Candidate cache key.
     *
     * @return bool `true` when `$key` is a well-formed cache key
     *
     * @phpstan-assert-if-true non-empty-string $key
     */
    function is_cache_key(
        string $key,
    ): bool {
        if ($key === '') {
            return false;
        }

        foreach (['-', '.', ':'] as $separator) {
            if (\str_contains($key, $separator . $separator)) {
                return false;
            }

            if ($separator === $key[0] || $separator === $key[\strlen($key) - 1]) {
                return false;
            }
        }

        return _match_charset(
            $key,
            \CHARSET_ALNUM . '.-:',
        );
    }

    /**
     * Validates URI / URI-reference string shape (RFC 3986-inspired).
     *
     * - By default requires an absolute URI with a scheme of at least two characters.
     * - Accepts hierarchical and opaque forms (`https://…`, `mailto:…`, `urn:…`, custom schemes).
     * - ASCII structure only; non-ASCII hosts (IDN) are rejected — use punycode first.
     * - Percent-encoding must be well-formed (`%` + two hex digits).
     * - Shape only: no DNS lookup, no HTTP probe, no scheme allowlist.
     *
     * When `$allowRelative` is `true`, also accepts RFC 3986 `relative-ref` forms:
     * path-absolute (`/assets/app.css`), path-noscheme (`assets/app.css`),
     * network-path (`//cdn.example.test/x`), and query/fragment-only (`?q=1`, `#f`).
     *
     * Single-character schemes (`C:/…`, `x://…`) are rejected by default as a
     * drive-letter / footgun guard. Pass `$allowSingleCharScheme = true` for
     * RFC-faithful acceptance (almost never needed for public URL spaces).
     *
     * @param string $value                 Candidate URI or URI-reference
     * @param bool   $allowRelative         Accept relative-ref forms (default `false`)
     * @param bool   $allowSingleCharScheme Accept 1-character schemes (default `false`)
     *
     * @return bool `true` when `$value` matches the selected URI shape rules
     *
     * @phpstan-assert-if-true non-empty-string $value
     */
    function is_valid_uri(
        string $value,
        bool $allowRelative = false,
        bool $allowSingleCharScheme = false,
    ): bool {
        return _scan_uri($value, $allowRelative, $allowSingleCharScheme) !== null;
    }

    /**
     * Validates hierarchical network URL string shape.
     *
     * Requires a successful absolute URI scan **and** an authority form
     * (`scheme://…`) with a non-empty host. Opaque URIs (`mailto:…`, `urn:…`) fail.
     * Relative references are never accepted — use {@see is_valid_uri()} with
     * `$allowRelative` for those.
     *
     * This is **not** a WHATWG URL Living Standard parser — it is a practical
     * authority-based subset of the URI grammar used for http(s)-style strings.
     *
     * - Shape only: no DNS lookup, no HTTP probe, no scheme allowlist.
     * - ASCII / pct-encoded only; IDN unicode hosts are rejected.
     * - Single-character schemes are always rejected (same antifootgun as
     *   {@see is_valid_uri()} defaults).
     *
     * @param string $value Candidate URL
     *
     * @return bool `true` when `$value` is an absolute URI with `://` and a non-empty host
     *
     * @phpstan-assert-if-true non-empty-string $value
     */
    function is_valid_url(
        string $value,
    ): bool {
        $scan = _scan_uri($value);

        return $scan !== null && $scan['authority'] && $scan['host'] !== '';
    }
}

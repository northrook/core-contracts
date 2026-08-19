<?php

declare(strict_types=1);

namespace Northrook\Parameter;

use Northrook\Context;
use Northrook\InvalidArgumentException;

/**
 * Secrecy tier for secret-aware values.
 *
 * ## Contexts
 * - **Runtime use**: in-process at the call site; arguments, HTTP auth, DB connect, etc.
 * - **Debug**: traces, logs, {@see object::__debugInfo()}, {@see \Northrook\Snapshot}, etc.
 * - **Serialization**: {@see object::__serialize()}, {@see \json_encode()}, {@see \Northrook\JSON}, etc.
 * - **Persistence**: safe storage such as database, {@see \Northrook\Context::$varDirectory}, etc.
 */
enum Secret implements \JsonSerializable
{
    /**
     * High-trust material: plaintext only inside the trust boundary.
     *
     * - Runtime use, HTTP auth, DB connect, etc.
     * - Trusted, non-public exports.
     * - Must be redacted or omitted everywhere else.
     */
    case CREDENTIAL;

    /**
     * Sensitive material.
     *
     * Broadly similar to the native {@see \SensitiveParameter} attribute.
     *
     * - Can be serialized, used in URLs / API calls, etc. (including when public).
     * - Must be redacted or omitted from logs, traces, dumps.
     */
    case SENSITIVE;

    public static function from(
        string|self $value,
    ): self {
        if ($value instanceof self) {
            return $value;
        }

        return match (\strtolower($value)) {
            'sensitive'  => Secret::SENSITIVE,
            'credential' => Secret::CREDENTIAL,
            default      => throw new InvalidArgumentException(
                message: 'Unable to resolve ' . self::class . ' from value.',
                context: ['value' => $value],
            ),
        };
    }

    /**
     * JSON wire form: lowercase case name (`sensitive` / `credential`).
     */
    public function jsonSerialize(): string
    {
        return \strtolower($this->name);
    }

    /**
     * Debug-out / non-authoritative redaction mask.
     *
     * - {@see CREDENTIAL} always returns `[secret::credential]`.
     * - {@see SENSITIVE} uses `$mask`, or a type label derived from `$value` when `$mask` is null.
     *
     * @param mixed               $payload  value being redacted, used for type labeling
     * @param non-empty-string[]  $context  optional tags / attribute class names
     * @param null|string         $mask     override label inside `[secret::…]`
     *
     * @return non-empty-string
     */
    public function __invoke(
        mixed       $payload,
        array       $context = [],
        null|string $mask = null,
    ): string {
        // credentials are always redacted
        if ($this === self::CREDENTIAL) {
            return '[secret::credential]';
        }

        // potential contextual overrides
        if (\in_array(\SensitiveParameter::class, $context, true)) {
            $mask = \SensitiveParameter::class;
        }

        // fallback in case the mask is nulled
        $mask ??= Context::isDebug()
            ? \debug_value_type($payload)
            : \gettype($payload);

        return "[secret::{$mask}]";
    }
}

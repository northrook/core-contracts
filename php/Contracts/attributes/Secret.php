<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts;

/**
 * Marks a value as secret.
 *
 * Both types are scrubbed from logs and non-authoritative dumps.
 *
 * Runtime consumption differs by {@see Secret::$type}:
 * - {@see Secret::SENSITIVE} internal use only; passwords, nonces, API keys, etc.
 * - {@see Secret::CREDENTIAL} may be passed into URLs, API calls, etc.
 *
 * Dump placeholders go through {@see redact()}, which honours
 * {@see \Northrook\Contracts::$secretRedactor} when registered.
 *
 * @phpstan-type Type 'sensitive'|'credential'
 */
#[\Attribute]
final readonly class Secret
{
    /**
     * Internal use at runtime only.
     *
     * Must not be exposed in public scope.
     */
    public const string SENSITIVE = 'sensitive';

    /**
     * May be consumed in URLs, API calls, etc.
     */
    public const string CREDENTIAL = 'credential';

    /**
     * Condition used when redacting a {@see \SensitiveParameter} slot.
     */
    public const string CONDITION_SENSITIVE_PARAMETER = 'sensitive-parameter';

    public mixed $value;

    /** @var Type */
    public string $type;

    /**
     * Optional app-defined tag for custom {@see Redactor} branches
     * (`'db-dsn'`, `'oauth-token'`, …).
     */
    public null|string $condition;

    /**
     * @param mixed        $value
     * @param null|Type    $type
     * @param null|string  $condition
     *
     * @throws InvalidArgumentException When `$type` is not {@see Type}
     */
    public function __construct(
        mixed       $value = null,
        null|string $type = null,
        null|string $condition = null,
    ) {
        $type ??= self::SENSITIVE;

        if (! ( $type === self::CREDENTIAL || $type === self::SENSITIVE )) {
            unset($value);
            throw new InvalidArgumentException(
                message: "Invalid secret type '{$type}'.",
                context: ['type' => $type],
            );
        }

        $this->value     = $value;
        $this->type      = $type;
        $this->condition = $condition;
    }

    /**
     * Placeholder for dumps / non-authoritative serialization.
     *
     * Uses {@see \Northrook\Contracts::$secretRedactor} when set, otherwise
     * {@see Redactor}. A {@see self} value unwraps to its inner value,
     * type, and condition (explicit `$type` / `$condition` arguments win when non-null).
     *
     * @param mixed        $value
     * @param null|Type    $type
     * @param null|string  $condition  App-specific branch key for custom redactors
     *
     * @return string
     */
    public static function redact(
        mixed       $value,
        null|string $type = null,
        null|string $condition = null,
    ): string {
        if ($value instanceof self) {
            $type      ??= $value->type;
            $condition ??= $value->condition;
            $value     = $value->value;
        }

        $type ??= self::SENSITIVE;

        if (! ( $type === self::CREDENTIAL || $type === self::SENSITIVE )) {
            throw new InvalidArgumentException(
                message: "Invalid secret type '{$type}'.",
                context: ['type' => $type],
            );
        }

        $redactor = Contracts::tryGet()->secretRedactor ?? new Redactor;

        return $redactor($value, $type, $condition);
    }

    /**
     * Built-in dump placeholder via {@see Redactor}.
     *
     * @param mixed        $value
     * @param Type         $type
     * @param null|string  $condition
     *
     * @return string
     */
    public static function defaultRedactor(
        mixed       $value,
        string      $type,
        null|string $condition = null,
    ): string {
        return ( new Redactor )($value, $type, $condition);
    }
}

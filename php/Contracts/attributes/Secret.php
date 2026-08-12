<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Marks a property or parameter as secret-aware.
 *
 * Builds a frozen {@see Value\Secret} policy (type + conditions). Does not wrap a payload —
 * use {@see Value} for secret-aware instances.
 *
 * - Default {@see Value\Secret::SENSITIVE} — dump / debug hygiene (≈ {@see \SensitiveParameter});
 *   may still serialize (including on the public wire).
 * - {@see Value\Secret::CREDENTIAL} — trusted persist OK when `!{@see \Northrook\AppEnv::isPublic()}`;
 *   refused on the public wire (Serializer throws by default).
 *
 * @see Value\Secret
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Secret
{
    public Value\Secret $secret;

    public const string CREDENTIAL = Value\Secret::CREDENTIAL;

    public const string SENSITIVE = Value\Secret::SENSITIVE;

    /**
     * @param 'sensitive'|'credential'  $type
     * @param non-empty-string          ...$conditions
     */
    public function __construct(
        string    $type = Value\Secret::SENSITIVE,
        string ...$conditions,
    ) {
        $this->secret = new Value\Secret(
            type      : $type,
            conditions: $conditions,
            immutable : true,
        );
    }

    /**
     * Debug-out / non-authoritative redaction via the built policy.
     *
     * @template T
     *
     * @param T  $value
     *
     * @return T|string
     */
    public function __invoke(
        mixed $value,
    ): mixed {
        return ( $this->secret )($value);
    }
}

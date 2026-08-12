<?php

declare(strict_types=1);

namespace Northrook\Contracts\Value;

use Northrook\AppEnv;
use Northrook\Contracts;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Serializable;

/**
 * Policy metadata for secret-aware values:
 * - Provides secrecy type and optional conditions.
 * - Does not hold a payload, use {@see \Northrook\Contracts\Value} for that.
 * - Invoke the {@see Secret} instance to redact a value based on type and conditions.
 *
 * ## Contexts
 * - **Runtime use**: in-process at the call site; arguments, HTTP auth, DB connect, etc.
 * - **Debug**: traces, logs, {@see object::__debugInfo()}, {@see \Northrook\Contracts\Snapshot}, etc.
 * - **Serialization**: {@see object::__serialize()}, {@see \json_encode()}, {@see \Northrook\Contracts\JSON}, etc.
 * - **Persistence**: Safe storage, such as database, {@see Contracts::$varDirectory}, etc.
 *
 * ## Types
 * - {@see SENSITIVE} — redact debug out; runtime + serialize/JSON + persist OK
 * - {@see CREDENTIAL} — redact debug out; plaintext serialize/persist when
 *   `!{@see AppEnv::isPublic()}`; refused on the public wire (throws by default)
 *
 * ## Mutability
 * Mutations throw {@see RuntimeException} when {@see AppEnv::isPublic()} or {@see isFrozen()}.
 *
 * @phpstan-type SecretType "sensitive"|"credential"
 * @phpstan-type SecretConditions array<non-empty-lowercase-string, non-empty-string>
 */
final class Secret implements \Stringable, Serializable
{
    public const array TYPES = [
        'sensitive',
        'credential',
    ];

    /**
     * High-trust material: plaintext only inside the trust boundary.
     *
     * - Runtime use OK; debug always redacted.
     * - Trusted persist / serialize (`!{@see AppEnv::isPublic()}`) may carry plaintext.
     * - Public wire (`{@see AppEnv::isPublic()}`) — refuse via {@see \Northrook\Contracts\Serializer}.
     */
    public const string CREDENTIAL = 'credential';

    /**
     * Sensitive material.
     *
     * Broadly similar to the native {@see \SensitiveParameter} attribute.
     *
     * - Can be serialized, used in URLs / API calls, etc. (including when public).
     * - Must be redacted or omitted from logs, traces, dumps.
     */

    public const string SENSITIVE = 'sensitive';

    /**
     * Freeze state
     * - `null` during initialization.
     * - `false` allows mutability.
     * - `true` prevents mutability.
     */
    private null|bool $immutable = null;

    /**
     * @var SecretType
     */
    public private(set) string $type;

    /**
     * @var SecretConditions
     */
    public private(set) array $conditions = [];

    /**
     * @param SecretType                           $type
     * @param non-empty-string|non-empty-string[]  $conditions
     * @param bool                                 $immutable
     */
    public function __construct(
        string       $type,
        string|array $conditions = [],
        bool         $immutable = false,
    ) {
        $this->setType($type);

        if ($conditions !== []) {
            if (\is_array($conditions)) {
                $this->addCondition(...\array_values($conditions));
            } else {
                $this->addCondition($conditions);
            }
        }

        $this->immutable = $immutable;
    }

    /**
     * Build a {@see Secret}, or `null` when `$value` is null.
     *
     * Passing an existing {@see \Northrook\Contracts\Value\Secret}:
     * - uses the existing `$value` type.
     * - merges its conditions with any passed `$conditions`.
     * - inherits freeze (`$immutable ||` source frozen); cannot unfreeze via `from`.
     *
     * @param null|string|self|"sensitive"|"credential"  $value
     * @param non-empty-string|non-empty-string[]        $conditions
     * @param bool                                       $immutable
     *
     * @return null|\Northrook\Contracts\Value\Secret
     */
    public static function from(
        null|string|self $value,
        string|array     $conditions = [],
        bool             $immutable = false,
    ): null|self {
        if ($value === null) {
            return null;
        }

        if ($value instanceof self) {
            $conditions = [
                ...$value->conditions,
                ...( \is_array($conditions) ? $conditions : [$conditions] ),
            ];
            $type      = $value->type;
            $immutable = $immutable || ( $value->immutable ?? false );
        } else {
            $type = \strtolower($value);
        }

        Secret::isValidType($type);

        return new self(
            $type,
            $conditions,
            $immutable,
        );
    }

    /**
     * Lock type and conditions.
     */
    public function freeze(): self
    {
        $this->immutable = true;
        return $this;
    }

    /**
     * Whether the instance is frozen.
     */
    public function isFrozen(): bool
    {
        return $this->immutable ?? false;
    }

    /**
     * @param SecretType  $type
     *
     * @return \Northrook\Contracts\Value\Secret
     */
    public function setType(
        string $type,
    ): self {
        $this->assertMutable();

        $set = \strtolower($type);

        Secret::isValidType($set);

        $this->type = $set;

        return $this;
    }

    /**
     * Check if the secret has a condition.
     *
     * @param string $condition
     * @return bool
     */
    public function hasCondition(
        string $condition,
    ): bool {
        return \array_key_exists(
            \strtolower($condition),
            $this->conditions,
        );
    }

    /**
     * Remove conditions matching any of the given strings.
     *
     * @return int  Number of conditions removed
     */
    public function removeCondition(
        string ...$condition,
    ): int {
        $this->assertMutable();

        $removed = 0;
        foreach ($condition as $value) {
            $key = \strtolower($value);
            if (\array_key_exists(
                $key,
                $this->conditions,
            )) {
                unset($this->conditions[$key]);
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Re-adding an existing condition moves it to the front, bumping its priority.
     *
     * @param non-empty-string  ...$condition
     */
    public function addCondition(
        string ...$condition,
    ): self {
        $this->assertMutable();

        foreach ($condition as $value) {
            if ($value === '' || \trim($value) !== $value) {
                throw new InvalidArgumentException(
                    message: 'Secret condition must be a non-empty string, with no bracketing whitespace.',
                    context: ['condition' => $value, 'conditions' => $condition],
                );
            }

            $key = \strtolower($value);

            // If the condition already exists, move it to the front, bumping its priority.
            if (\array_key_exists($key, $this->conditions)) {
                unset($this->conditions[$key]);
                $this->conditions = [$key => $value, ...$this->conditions];
            } else {
                $this->conditions[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * @return SecretType
     */
    public function __toString(): string
    {
        return $this->type;
    }

    /**
     * Serialize / JSON: this **policy** (type / conditions / freeze), not a secret payload.
     *
     * @return array{type: SecretType, conditions: SecretConditions, immutable: null|bool}
     */
    public function __serialize(): array
    {
        return [
            'type'       => $this->type,
            'conditions' => $this->conditions,
            'immutable'  => $this->immutable,
        ];
    }

    /**
     * @return array{type: SecretType, conditions: SecretConditions, immutable: null|bool}
     */
    public function jsonSerialize(): array
    {
        return $this->__serialize();
    }

    /**
     * Restore policy metadata under a temporary init window, then reapply freeze.
     *
     * @param array{type?: SecretType, conditions?: non-empty-string[], immutable?: null|bool}  $data
     */
    public function __unserialize(
        array $data,
    ): void {
        $this->immutable = null;
        $this->setType($data['type'] ?? self::SENSITIVE);
        $this->addCondition(...\array_values($data['conditions'] ?? []));
        $this->immutable = $data['immutable'] ?? false;
    }

    /**
     * Debug-out / non-authoritative redaction via {@see Contracts::$secretRedactor} or {@see Redactor}.
     *
     * @template T
     *
     * @param T  $value
     *
     * @return T|string  Default redactor returns a `[type::mask]` string
     */
    public function __invoke(
        mixed $value,
    ): mixed {
        return ( Contracts::tryGet()->secretRedactor ?? new Redactor )($value, $this);
    }

    /**
     * Blocks mutation when frozen, or when unfrozen but {@see AppEnv::isPublic()}.
     *
     * `immutable === null` (construct / unserialize) always allows mutation.
     */
    private function assertMutable(): void
    {
        $mutationError = match (true) {
            $this->immutable !== null && AppEnv::isPublic() => ' is not mutable in public environment.',
            $this->immutable === true => ' is immutable.',
            default => false,
        };

        if ($mutationError === false) {
            return;
        }

        throw new RuntimeException(
            message: __CLASS__ . $mutationError,
            context: [
                'type'       => $this->type,
                'conditions' => $this->conditions,
                'immutable'  => $this->immutable,
            ],
        );
    }

    /**
     * @param string  $string
     *
     * @throws InvalidArgumentException
     *
     * @phpstan-assert SecretType $string
     */
    private static function isValidType(
        string $string,
    ): void {
        if ($string === self::CREDENTIAL || $string === self::SENSITIVE) {
            return;
        }

        throw new InvalidArgumentException(
            message: "Invalid secret type `{$string}`.",
            context: ['type' => $string],
        );
    }
}

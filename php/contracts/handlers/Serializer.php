<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\AppEnv;

/**
 * Single serialization authority for {@see Serializable} implementations.
 *
 * One hierarchy-aware property walk feeds every channel; per-channel policy decides
 * redaction. Magic methods are `final` — implementers customize through the
 * `protected` hooks, never by overriding runtime entry points.
 *
 * | Channel | Tier policy |
 * |---------|-------------|
 * | {@see __debugInfo()} | any {@see Value\Secret} redacted via {@see redactForDebug()} |
 * | {@see __serialize()} / {@see jsonSerialize()} | both tiers plaintext when `!{@see AppEnv::isPublic()}`;
 *   {@see Value\Secret::CREDENTIAL} refused via {@see redactForSerialize()} when public |
 *
 * Trust boundary is {@see AppEnv::isPublic()} (HTTP / outward wire). Trusted persist —
 * caches, VarExporter, `$varDirectory` — runs with `public=false` and may carry
 * credentials. Debug out is never authoritative regardless of the flag.
 *
 * Secret policies resolve from:
 * - {@see PropertyAttributes::redaction()} — `#[Secret]` / `#[\SensitiveParameter]`
 *   on the property or its matching constructor parameter.
 * - The overridable {@see resolvePropertySecret()} hook — {@see Value} uses this to
 *   point its own `$secret` policy at its `value` property.
 *
 * Nested objects are never special-cased: engine recursion invokes each nested
 * {@see Serializable}'s own final methods, keeping redaction uniform.
 *
 * Public-wire credential refusal is skipped only while {@see Exporter} has an
 * active override (the exclusive bypass). Direct serialize / JSON remain refused.
 *
 * Note: {@see castValue()} applies to all channels, so `__serialize` output for cast
 * properties restores raw — override the hook or restore logic where round-trip
 * fidelity matters.
 *
 * @phpstan-require-implements \Northrook\Contracts\Serializable
 */
trait Serializer
{
    /**
     * @return array<string, mixed>
     */
    final public function __serialize(): array
    {
        $state  = [];
        $public = ! Exporter::isOverrideActive() && AppEnv::isPublic();

        foreach ($this->walkProperties() as $name => [$value, $property]) {
            $secret = $this->resolvePropertySecret($name, $value, $property);

            if ($public && $secret?->type === Value\Secret::CREDENTIAL) {
                $state[$name] = $this->redactForSerialize($name, $value, $secret);
            } else {
                $state[$name] = $this->castValue($value);
            }
        }

        return $state;
    }

    /**
     * Restores state via reflection: initializes `readonly` properties and runs
     * property hooks (set hooks re-validate, e.g. {@see Parameter}). Unknown keys
     * are ignored.
     *
     * @param array<string, mixed> $data
     */
    final public function __unserialize(array $data): void
    {
        foreach (self::propertyMap($this) as $name => $property) {
            if (! \array_key_exists($name, $data)) {
                continue;
            }

            $property->setValue($this, $data[$name]);
        }
    }

    /**
     * Debug out is never authoritative: any secret tier is masked.
     *
     * @return array<string, mixed>
     */
    final public function __debugInfo(): array
    {
        $info = [];

        foreach ($this->walkProperties(includeUninitialized: true) as $name => [$value, $property]) {
            if ($value === self::UNINITIALIZED) {
                $info[$name] = '[uninitialized]';
                continue;
            }

            $secret = $this->resolvePropertySecret($name, $value, $property);

            $info[$name] = $secret !== null
                ? $this->redactForDebug($value, $secret)
                : $this->castValue($value);
        }

        return $info;
    }

    /**
     * JSON is a serialize channel: mirrors {@see __serialize()}.
     *
     * @return array<string, mixed>
     */
    final public function jsonSerialize(): array
    {
        return $this->__serialize();
    }

    /**
     * Secret policy guarding a property, or `null` when the property is plain.
     *
     * Default resolves `#[Secret]` / `#[\SensitiveParameter]` from the property or
     * its matching constructor parameter. Override to supply policies from other
     * sources — e.g. {@see Value} returns `$this->secret` for its `value` property.
     */
    protected function resolvePropertySecret(
        string              $name,
        mixed               $value,
        \ReflectionProperty $property,
    ): null|Value\Secret {
        return PropertyAttributes::redaction(
            new \ReflectionClass($this),
            $property,
        );
    }

    /**
     * Public-wire handling for {@see Value\Secret::CREDENTIAL}.
     *
     * Invoked only when {@see AppEnv::isPublic()}. Default throws — credentials must
     * not leave via outward serialize/JSON. Override to intentionally drop / mask
     * for a controlled lossy public export path. Trusted persist (`!isPublic()`)
     * never reaches this hook.
     *
     * @throws RuntimeException
     */
    protected function redactForSerialize(
        string       $name,
        mixed        $value,
        Value\Secret $secret,
    ): mixed {
        throw new RuntimeException(
            message: "Cannot serialize credential property \${$name}.",
            context: [
                'class'    => $this::class,
                'property' => $name,
                'type'     => $secret->type,
                'public'   => true,
            ],
        );
    }

    /**
     * Debug-channel redaction. Default invokes the policy mask
     * ({@see Contracts::$secretRedactor} or {@see Value\Redactor}).
     */
    protected function redactForDebug(
        mixed        $value,
        Value\Secret $secret,
    ): mixed {
        return $secret($value);
    }

    /**
     * Value casts applied in every channel, after redaction checks.
     */
    protected function castValue(mixed $value): mixed
    {
        if ($value instanceof Timestamp) {
            return $value->string;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        return $value;
    }

    private const string UNINITIALIZED = "\0uninitialized\0";

    /**
     * Walks all declared instance properties, most specific declaration first.
     *
     * @return iterable<string, array{mixed, \ReflectionProperty}>
     */
    private function walkProperties(bool $includeUninitialized = false): iterable
    {
        foreach (self::propertyMap($this) as $name => $property) {
            if (! $property->isInitialized($this)) {
                if ($includeUninitialized) {
                    yield $name => [self::UNINITIALIZED, $property];
                }
                continue;
            }

            yield $name => [$property->getValue($this), $property];
        }
    }

    /**
     * @return array<string, \ReflectionProperty> Non-static properties, child-first
     */
    private static function propertyMap(object $subject): array
    {
        $map        = [];
        $reflection = new \ReflectionClass($subject);

        while ($reflection !== false) {
            foreach ($reflection->getProperties() as $property) {
                // Virtual (hook-only) properties have no backing store — nothing to serialize.
                if ($property->isStatic() || $property->isVirtual()) {
                    continue;
                }

                $name = $property->getName();

                // Child declarations win; only record a property at its declaring class.
                if (\array_key_exists($name, $map) || $property->getDeclaringClass()->name !== $reflection->name) {
                    continue;
                }

                $map[$name] = $property;
            }

            $reflection = $reflection->getParentClass();
        }

        return $map;
    }
}

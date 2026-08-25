<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Single serialization authority for {@see Serializable} implementations.
 *
 * One hierarchy-aware property walk feeds every channel; per-channel policy decides
 * redaction. Magic methods are `final` — implementers customize through the
 * `protected` hooks, never by overriding runtime entry points.
 *
 * | Channel | Tier policy |
 * |---------|-------------|
 * | {@see __debugInfo()} | any {@see \Northrook\Parameter\Secret} redacted via {@see redactForDebug()} |
 * | {@see __serialize()} / {@see jsonSerialize()} | both tiers plaintext when {@see Context::isTrusted()};
 *   {@see \Northrook\Parameter\Secret::CREDENTIAL} refused via {@see redactForSerialize()} when {@see Context::isUntrusted()} |
 *
 * Trust boundary is {@see Context::isUntrusted()} (Failsafe, {@see KernelContext::Request},
 * {@see KernelContext::Response}). Trusted persist — caches, VarExporter, `$varDirectory` —
 * runs under Boot / Compile / Runtime and may carry credentials. Debug out is never
 * authoritative regardless of context.
 *
 * Secret policies resolve from {@see Redaction::for()} (instance `$secret` on `value`, else
 * `#[Secret]` / `#[\SensitiveParameter]`). Override {@see resolvePropertySecret()} to change that.
 *
 * Nested objects are never special-cased: engine recursion invokes each nested
 * {@see Serializable}'s own final methods, keeping redaction uniform.
 *
 * Outbound credential refusal is skipped only while {@see Exporter} has an
 * active override (the exclusive bypass). Direct serialize / JSON / {@see guardExport()}
 * remain refused. {@see Exportable::_export()} is implementer-owned — call {@see guardExport()}
 * first; the trait does not provide `_export`.
 *
 * Note: {@see castValue()} applies to all channels, so `__serialize` output for cast
 * properties restores raw — override the hook or restore logic where round-trip
 * fidelity matters.
 *
 * @phpstan-import-type SecretRedaction from Redaction
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
        $state    = [];
        $outbound = ! Exporter::isOverrideActive() && Context::isUntrusted();

        foreach ($this->walkProperties() as $name => [$value, $property]) {
            $redaction = $this->resolvePropertySecret($name, $value, $property);

            if ($outbound && $redaction !== null && $redaction['secret'] === Parameter\Secret::CREDENTIAL) {
                $state[$name] = $this->redactForSerialize($name, $value, $redaction['secret']);
            }
            else {
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
    final public function __unserialize(
        array $data,
    ): void {
        foreach (self::propertyMap($this) as $name => $property) {
            if (! \array_key_exists($name, $data)) {
                continue;
            }

            $property->setValue($this, self::coercePropertyValue($property, $data[$name]));
        }
    }

    private static function coercePropertyValue(
        \ReflectionProperty $property,
        mixed               $value,
    ): mixed {
        if (! \is_string($value)) {
            return $value;
        }

        $type = $property->getType();

        if (! $type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return $value;
        }

        /** @var class-string<\BackedEnum|\UnitEnum> $className */
        $className = $type->getName();

        if (! \enum_exists($className)) {
            return $value;
        }

        if (\is_subclass_of($className, \BackedEnum::class)) {
            return $className::from($value);
        }

        foreach ($className::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        return $value;
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

            $redaction = $this->resolvePropertySecret($name, $value, $property);

            $info[$name] = $redaction !== null
                ? $this->redactForDebug($value, $redaction['secret'], $redaction['tags'])
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
     * Secret tier + tag seeds guarding a property, or `null` when the property is plain.
     *
     * @return null|SecretRedaction
     */
    protected function resolvePropertySecret(
        int|string          $propertyIndex,
        mixed               $propertyValue,
        \ReflectionProperty $reflectionProperty,
    ): null|array {
        return Redaction::for($this, (string) $propertyIndex, $reflectionProperty);
    }

    /**
     * Outbound credential check for {@see Exportable::_export()}.
     *
     * No-op when {@see Exporter} is active or the current {@see KernelContext} is trusted.
     * Otherwise throws via {@see redactForSerialize()} for any CREDENTIAL property.
     *
     * @throws RuntimeException
     */
    final protected function guardExport(): void
    {
        if (Exporter::isOverrideActive() || Context::isTrusted()) {
            return;
        }

        foreach ($this->walkProperties() as $name => [$value, $property]) {
            $redaction = $this->resolvePropertySecret($name, $value, $property);

            if ($redaction !== null && $redaction['secret'] === Parameter\Secret::CREDENTIAL) {
                $this->redactForSerialize($name, $value, $redaction['secret']);
            }
        }
    }

    /**
     * Outbound handling for {@see \Northrook\Parameter\Secret::CREDENTIAL}.
     *
     * Invoked only when {@see Context::isUntrusted()}. Default throws — credentials must
     * not leave via outward serialize/JSON/`_export`. Override to intentionally drop / mask
     * for a controlled lossy public export path. Trusted persist never reaches this hook.
     *
     * @throws RuntimeException
     */
    protected function redactForSerialize(
        string           $name,
        mixed            $value,
        Parameter\Secret $secret,
    ): mixed {
        throw new RuntimeException(
            message: "Cannot serialize credential property \${$name}.",
            context: [
                'class'    => $this::class,
                'property' => $name,
                'type'     => $secret,
                'context'  => Context::tryGet()?->currentContext,
            ],
        );
    }

    /**
     * Debug-channel redaction. Default invokes {@see Redaction::mask()}.
     *
     * @param list<non-empty-string>  $tags  property / attribute tag seeds
     */
    protected function redactForDebug(
        mixed            $value,
        Parameter\Secret $secret,
        array            $tags = [],
    ): mixed {
        return Redaction::mask($value, $secret, $tags, $this);
    }

    /**
     * Value casts applied in every channel, after redaction checks.
     */
    protected function castValue(
        mixed $value,
    ): mixed {
        if ($value instanceof Timestamp) {
            return $value->string;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return $value;
    }

    private const string UNINITIALIZED = "\0uninitialized\0";

    /**
     * Walks all declared instance properties, most specific declaration first.
     *
     * @return iterable<string, array{mixed, \ReflectionProperty}>
     */
    private function walkProperties(
        bool $includeUninitialized = false,
    ): iterable {
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
    private static function propertyMap(
        object $subject,
    ): array {
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

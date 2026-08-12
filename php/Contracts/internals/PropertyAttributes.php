<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * Resolves attributes declared on a property and/or its matching constructor parameter.
 *
 * Placement is irrelevant: either site is enough. A non-promoted property that
 * shares an attribute FQCN with its constructor parameter is a conflict.
 *
 * Promoted properties are exempt from the conflict throw — PHP mirrors a single
 * declaration onto both reflection sites when the attribute target allows it.
 *
 * Results are cached per class + property name.
 */
final class PropertyAttributes implements Resettable
{
    /**
     * @var array<class-string, array<string, list<\ReflectionAttribute<object>>>>
     */
    private static array $resolved = [];

    /**
     * @var array<class-string, int>
     */
    private static array $targetFlags = [];

    /**
     * @param \ReflectionClass<covariant object> $class
     *
     * @return list<\ReflectionAttribute<object>>
     *
     * @throws RuntimeException When the same attribute FQCN is declared on both sites
     */
    public static function resolve(
        \ReflectionClass    $class,
        \ReflectionProperty $property,
    ): array {
        $className    = $class->getName();
        $propertyName = $property->getName();

        if (isset(self::$resolved[$className][$propertyName])) {
            return self::$resolved[$className][$propertyName];
        }

        try {
            $onProperty = self::applicableAttributes(
                $property->getAttributes(),
                \Attribute::TARGET_PROPERTY,
            );

            $onParameter = [];
            foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
                if ($parameter->getName() !== $propertyName) {
                    continue;
                }

                $onParameter = self::applicableAttributes(
                    $parameter->getAttributes(),
                    \Attribute::TARGET_PARAMETER,
                );
                break;
            }
        } catch (\ReflectionException $exception) {
            throw new RuntimeException(
                message : "Failed to resolve attributes for {$className}::\${$propertyName}",
                context : ['class' => $className, 'property' => $propertyName],
                previous: $exception,
            );
        }

        $byProperty  = [];
        $byParameter = [];
        foreach ($onProperty as $attribute) {
            $byProperty[$attribute->getName()] = $attribute;
        }
        foreach ($onParameter as $attribute) {
            $byParameter[$attribute->getName()] = $attribute;
        }

        $duplicates = \array_intersect_key($byProperty, $byParameter);

        if ($duplicates !== [] && ! $property->isPromoted()) {
            $names = \implode(', ', \array_keys($duplicates));
            throw new RuntimeException(
                message: "Attribute [{$names}] declared on both {$className}::\${$propertyName}" . ' and its constructor parameter.',
            );
        }

        // Promoted mirrors share FQCNs; keep property entries and add param-only ones.
        return self::$resolved[$className][$propertyName] = \array_values($byProperty + $byParameter);
    }

    /**
     * Combined secret policy from every {@see Secret} / {@see \SensitiveParameter}.
     *
     * Multiple hits merge defensively:
     * - type: {@see Value\Secret::CREDENTIAL} wins over {@see Value\Secret::SENSITIVE}
     * - conditions: union (order of first appearance)
     * - result is always frozen
     *
     * Duplicate {@see Secret} is unlikely (attribute is not repeatable; dual-site
     * same-FQCN already conflicts in {@see resolve()}), but when present the same
     * merge applies rather than throwing.
     *
     * @param \ReflectionClass<covariant object> $class
     */
    public static function redaction(
        \ReflectionClass    $class,
        \ReflectionProperty $property,
    ): null|Value\Secret {
        $type       = null;
        $conditions = [];

        foreach (self::resolve($class, $property) as $attribute) {
            $name = $attribute->getName();

            if ($name === Secret::class) {
                /** @var Secret $instance */
                $instance = $attribute->newInstance();
                $policy   = $instance->secret;

                $type = $type === Value\Secret::CREDENTIAL || $policy->type === Value\Secret::CREDENTIAL
                    ? Value\Secret::CREDENTIAL
                    : Value\Secret::SENSITIVE;

                foreach ($policy->conditions as $condition) {
                    $conditions[$condition] = $condition;
                }

                continue;
            }

            if ($name === \SensitiveParameter::class) {
                $type ??= Value\Secret::SENSITIVE;
                $conditions[\SensitiveParameter::class] = \SensitiveParameter::class;
            }
        }

        if ($type === null) {
            return null;
        }

        return new Value\Secret(
            type      : $type,
            conditions: \array_values($conditions),
            immutable : true,
        );
    }

    /**
     * @param list<\ReflectionAttribute<object>> $attributes
     *
     * @return list<\ReflectionAttribute<object>>
     * @throws \ReflectionException
     */
    private static function applicableAttributes(
        array $attributes,
        int   $target,
    ): array {
        $applicable = [];

        foreach ($attributes as $attribute) {
            $flags = self::attributeTargetFlags($attribute->getName());

            if (( $flags & $target ) === $target) {
                $applicable[] = $attribute;
            }
        }

        return $applicable;
    }

    /**
     * @param class-string $attributeName
     *
     * @throws \ReflectionException
     */
    private static function attributeTargetFlags(
        string $attributeName,
    ): int {
        if (isset(self::$targetFlags[$attributeName])) {
            return self::$targetFlags[$attributeName];
        }

        $reflection = new \ReflectionClass($attributeName);
        $meta       = $reflection->getAttributes(\Attribute::class)[0] ?? null;

        return self::$targetFlags[$attributeName] = $meta?->newInstance()->flags ?? 0;
    }

    public static function reset(): void
    {
        self::$resolved    = [];
        self::$targetFlags = [];
    }
}

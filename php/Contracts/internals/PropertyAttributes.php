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
                message: "Attribute [{$names}] declared on both {$className}::\${$propertyName}"
                . ' and its constructor parameter.',
            );
        }

        // Promoted mirrors share FQCNs; keep property entries and add param-only ones.
        return self::$resolved[$className][$propertyName] = \array_values($byProperty + $byParameter);
    }

    /**
     * Redaction context for the first {@see Secret} / {@see \SensitiveParameter} attribute.
     *
     * @param \ReflectionClass<covariant object> $class
     *
     * @return null|array{type: Secret::SENSITIVE|Secret::CREDENTIAL, condition: null|string}
     */
    public static function redaction(
        \ReflectionClass    $class,
        \ReflectionProperty $property,
    ): null|array {
        foreach (self::resolve($class, $property) as $attribute) {
            $name = $attribute->getName();

            if ($name === Secret::class) {
                /** @var Secret $secret */
                $secret = $attribute->newInstance();

                return [
                    'type'      => $secret->type,
                    'condition' => $secret->condition,
                ];
            }

            if ($name === \SensitiveParameter::class) {
                return [
                    'type'      => Secret::SENSITIVE,
                    'condition' => Secret::CONDITION_SENSITIVE_PARAMETER,
                ];
            }
        }

        return null;
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

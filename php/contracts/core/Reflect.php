<?php

declare(strict_types=1);

namespace Northrook;

/**
 * @template T of object
 */
final readonly class Reflect
{
    /**
     * @param \ReflectionClass<T>  $class
     */
    private function __construct(
        /** @var \ReflectionClass<T> */
        public \ReflectionClass $class,
    ) {}

    /**
     * @param mixed  ...$arguments
     *
     * @return T
     */
    public function newInstance(
        mixed ...$arguments,
    ): object {
        try {
            return $this->class->newInstance(...$arguments);
        }
        catch (\Throwable $exception) {
            throw new RuntimeException(
                message : 'Failed to instantiate class ' . $this->class->name,
                context : ['class' => $this->class->name, 'arguments' => $arguments],
                previous: $exception,
            );
        }
    }

    /**
     * @return T
     */
    public function getInstance(): object
    {
        try {
            return $this->class->newInstanceWithoutConstructor();
        }
        catch (\Throwable $exception) {
            throw new RuntimeException(
                message : 'Failed to instantiate class ' . $this->class->name,
                context : ['class' => $this->class->name],
                previous: $exception,
            );
        }
    }

    /**
     * @return array<non-empty-string, \ReflectionProperty>
     */
    public function getPropertiesMap(
        bool         $onlyPublic = false,
        false|object $onlyInitialized = false,
        bool         $includeStatic = false,
        bool         $includeVirtual = false,
    ): array {
        $array = [];
        $class = $this->class;

        while ($class !== false) {
            foreach ($class->getProperties() as $property) {
                if (! $includeStatic && $property->isStatic()) {
                    continue;
                }

                if (! $includeVirtual && $property->isVirtual()) {
                    continue;
                }

                if ($onlyPublic && ! $property->isPublic()) {
                    continue;
                }

                if ($onlyInitialized && ! $property->isInitialized($onlyInitialized)) {
                    continue;
                }

                $name = $property->getName();

                if (\array_key_exists($name, $array) || $property->getDeclaringClass()->name !== $class->name) {
                    continue;
                }

                $array[$name] = $property;
            }

            $class = $class->getParentClass();
        }

        return $array;
    }

    /**
     * @return \ReflectionProperty[]
     */
    public function getProperties(): array
    {
        return $this->class->getProperties();
    }

    /**
     * @template A of object
     *
     * @param \Reflector       $from
     * @param class-string<A>  $name
     * @param int              $flags
     *
     * @return null|\ReflectionAttribute<A>
     */
    public static function attribute(
        \Reflector $from,
        string     $name,
        int        $flags = 0,
    ): null|object {
        if (\method_exists($from, 'getAttributes')) {
            $attributes = $from->getAttributes(
                $name,
                $flags,
            );
            if (\count($attributes) === 1) {
                $attribute = $attributes[0];

                if ($attribute instanceof \ReflectionAttribute) {
                    /** @var \ReflectionAttribute<A> $attribute */
                    return $attribute;
                }
            }
        }
        return null;
    }

    /**
     * @template TClass of object
     *
     * @param TClass|class-string<TClass>  $class
     *
     * @return \Northrook\Reflect<TClass>
     */
    public static function class(
        object|string $class,
    ): self {
        try {
            return new Reflect(new \ReflectionClass($class));
        }
        catch (\Throwable $exception) {
            throw new RuntimeException(
                message : 'Failed to reflect class ' . \debug_value_type($class),
                context : ['class' => $class],
                previous: $exception,
            );
        }
    }
}

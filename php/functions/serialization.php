<?php

declare(strict_types=1);

use Northrook\Reflect;
use Northrook\RuntimeException;

/**
 * @template T of object
 *
 * @param class-string<T>         $class
 * @param array<array-key,mixed>  $state
 *
 * @return T
 */
function instantiate(
    string $class,
    array  $state,
): object {
    try {
        $reflection = Reflect::class($class);
        $properties = $reflection->getPropertiesMap();
        $object     = $reflection->class->newInstanceWithoutConstructor();

        foreach ($state as $name => $value) {
            if (! \array_key_exists($name, $properties)) {
                throw new \ReflectionException('Unknown property ' . $class . '::$' . $name);
            }
            $properties[$name]->setValue($object, $value);
        }

        return $object;
    }
    catch (\Throwable $exception) {
        throw new RuntimeException(
            message : 'Failed to restore ' . $class . ' from state.',
            context : [
                'exception' => $exception,
                'class'     => $class,
                'state'     => $state,
            ],
            previous: $exception,
        );
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts;
use Northrook\Contracts\Exceptions\RuntimeException;

abstract readonly class DataObject implements \JsonSerializable, \Stringable
{
    protected function __construct(...$args)
    {
        // TODO: Validation when settled on the contract
    }

    final public function __toString(): string
    {
        return $this->jsonString();
    }

    final public function jsonString(
        bool $pretty = false,
        bool $escapeUnicode = false,
        bool $escapeSlashes = false,
        bool $preserveZeroFraction = true,
        null|callable $formatter = null,
    ): string {
        return JSON::encode(
            $this,
            pretty: $pretty,
            escapeUnicode: $escapeUnicode,
            escapeSlashes: $escapeSlashes,
            preserveZeroFraction: $preserveZeroFraction,
            formatter: $formatter,
        );
    }

    /**
     * @return array<string, mixed>
     */
    final public function jsonSerialize(): array
    {
        $className  = $this::class;
        $reflection = new \ReflectionClass($this);
        $properties = \get_object_vars($this);

        if (! $reflection->isFinal()) {
            throw new RuntimeException(
                message: "{$className} is a DataObject, and must be `final`.",
            );
        }

        try {
            foreach ($properties as $property => $value) {
                $reflected = $reflection->getProperty($property);

                if (! $reflected->isPublic()) {
                    // Consider whether this is relevant; DTOs shouldn't have internal properties.
                    // Allowing this for flexibility while settling on the contract.
                    Contracts::log()->warning(
                        "Property '{$className}->\${$property}' is not public, it will be ignored.",
                    );
                    unset($properties[$property]);
                    continue;
                }

                $attributes = $reflected->getAttributes();

                foreach ($attributes as $attribute) {
                    if (in_array(
                        $attribute->name,
                        [
                            \SensitiveParameter::class,
                            Contracts\Attributes\Secret::class,
                        ],
                    )) {
                        // TODO : This will be a settable handler from Contracts
                        $representation = $attribute->name === Contracts\Attributes\Secret::class
                            ? '[Secret::' . \gettype($value) . ']'
                            : \str_repeat('*', \is_string($value) ? \strlen($value) : 0);

                        $properties[$property] = $representation;
                    }
                }

                if ($value instanceof Timestamp) {
                    /** @var numeric-string $timestamp */
                    $timestamp             = $value->string;
                    $properties[$property] = $timestamp;
                }

                if ($value instanceof \BackedEnum) {
                    $properties[$property] = $value->value;
                }

                // More custom casts likely needed in time
            }
        } catch (\ReflectionException $exception) {
            throw RuntimeException::from($exception);
        }

        return $properties;
    }
}

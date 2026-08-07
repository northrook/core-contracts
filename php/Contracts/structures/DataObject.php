<?php

declare(strict_types=1);

namespace Northrook\Contracts;

abstract readonly class DataObject implements \JsonSerializable, \Stringable
{
    protected function __construct()
    {
        // TODO: Validation when settled on the contract
    }

    final public function __toString(): string
    {
        return $this->jsonString();
    }

    final public function jsonString(
        bool          $pretty = false,
        bool          $escapeUnicode = false,
        bool          $escapeSlashes = false,
        bool          $preserveZeroFraction = true,
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

                // Ignore non-public properties
                if (! $reflected->isPublic()) {
                    unset($properties[$property]);
                    continue;
                }

                if ($value instanceof Secret) {
                    $properties[$property] = Secret::redact($value);
                    continue;
                }

                $redaction = PropertyAttributes::redaction($reflection, $reflected);

                if ($redaction !== null) {
                    $properties[$property] = Secret::redact(
                        $value,
                        $redaction['type'],
                        $redaction['condition'],
                    );
                    continue;
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

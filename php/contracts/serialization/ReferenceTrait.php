<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Shared {@see Reference} boilerplate for denoting-string value objects.
 *
 * Host classes must declare a public non-empty `$value` and implement {@see Reference::normalize()}.
 *
 * @phpstan-require-implements \Northrook\Contracts\Reference
 *
 *
 *
 * @property-read non-empty-string $value
 */
trait ReferenceTrait
{
    /**
     * Same string as {@see $value}.
     *
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Whether `$value` is acceptable for this reference type.
     *
     * {@inheritDoc}
     */
    public static function isValid(
        string|\Stringable $value,
    ): bool {
        try {
            static::normalize($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Build a reference instance from `$value`, or `null` when invalid.
     *
     * {@inheritDoc}
     */
    public static function from(
        mixed $value,
        bool  $throw = false,
    ): null|static {
        if (! \is_string($value) && ! $value instanceof \Stringable) {
            if ($throw) {
                throw new InvalidArgumentException(
                    message: self::class . ' requires a string or Stringable value.',
                    context: [
                        'name'     => 'value',
                        'expected' => 'string|Stringable',
                        'received' => $value,
                    ],
                );
            }

            return null;
        }

        try {
            return new static($value);
        } catch (\Throwable $exception) {
            if ($throw) {
                throw new RuntimeException(
                    message : self::class . ' failed to initialize via from(): ' . $exception->getMessage(),
                    previous: $exception,
                );
            }

            return null;
        }
    }
}

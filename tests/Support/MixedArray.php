<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Narrows mixed snapshot/context values to arrays without @var overrides.
 */
final class MixedArray
{
    /**
     * @return array<array-key, mixed>
     */
    public static function from(
        mixed  $value,
        string $message = 'Expected array.',
    ): array {
        if (! \is_array($value)) {
            Assert::fail($message);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    public static function at(
        array      $array,
        string|int $key,
    ): array {
        return self::from(
            $array[$key] ?? null,
            'Expected array at key ' . \var_export($key, true) . '.',
        );
    }
}

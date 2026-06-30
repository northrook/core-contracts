<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use Northrook\Contracts\ContextSnapshot;
use Northrook\Contracts\ErrorHandler\ErrorBuffer;
use Throwable;

use const Northrook\Logger\LOG_LEVEL;

/**
 * Base {@see RuntimeException} with structured context for the error handler.
 *
 * Throw this directly only for internal invariant violations that indicate a bug.
 *
 * Subclasses may represent recoverable or operational failures worth catching at a boundary.
 */
class RuntimeException extends \RuntimeException
{
    /** @var array<string, ContextSnapshot|bool|float|int|null|string> */
    public readonly array $context;

    /**
     * @param null|array<string, mixed> $context
     */
    public function __construct(
        null|string $message = null,
        null|array $context = null,
        null|false|Throwable $previous = null,
        int $code = LOG_LEVEL['critical'],
    ) {
        $previousThrowable = $this->resolvePrevious($previous);
        $this->context     = self::snapshotContext(
            self::mergePhpErrorSnapshot($context),
        );

        $message ??= $previousThrowable?->getMessage() ?? 'Unspecified error';

        parent::__construct(
            message: $message,
            code: $code,
            previous: $previousThrowable,
        );
    }

    private function resolvePrevious(
        null|false|Throwable $previous,
    ): null|Throwable {
        if ($previous === false) {
            return null;
        }

        return $previous;
    }

    /**
     * @param null|array<string, mixed> $context
     *
     * @return null|array<string, mixed>
     */
    private static function mergePhpErrorSnapshot(
        null|array $context,
    ): null|array {
        $errors = ErrorBuffer::shared()->all();

        if ($errors === []) {
            return $context;
        }

        return ['phpErrors' => $errors, ...($context ?? [])];
    }

    /**
     * @param null|array<string, mixed> $context
     *
     * @return array<string, ContextSnapshot|bool|float|int|null|string>
     */
    private static function snapshotContext(
        null|array $context,
    ): array {
        if ($context === null) {
            return [];
        }

        $snapshots = [];

        foreach ($context as $key => $value) {
            $snapshots[$key] = self::snapshotContextValue($value);
        }

        return $snapshots;
    }

    private static function snapshotContextValue(
        mixed $value,
    ): ContextSnapshot|bool|float|int|null|string {
        if ($value instanceof ContextSnapshot) {
            return $value;
        }

        if (
            \is_array($value)
            || \is_object($value)
            || \is_resource($value)
            || \str_starts_with(\gettype($value), 'resource')
        ) {
            try {
                return ContextSnapshot::from($value);
            } catch (\Throwable) {
                return self::unsnapshotable($value);
            }
        }

        if (\is_bool($value) || \is_float($value) || \is_int($value) || \is_string($value) || $value === null) {
            return $value;
        }

        return self::unsnapshotable($value);
    }

    private static function unsnapshotable(
        mixed $value,
    ): string {
        if (\is_object($value)) {
            return '[Unsnapshotable: ' . $value::class . ']';
        }

        return '[Unsnapshotable: ' . \gettype($value) . ']';
    }
}

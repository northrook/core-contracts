<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

use Northrook\Contracts\ErrorHandler\ErrorBuffer;
use Northrook\Contracts\Snapshot;
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
    use ExceptionErrorSnapshot;
    use ExceptionContextSnapshot;

    /**
     * @param null|array<array-key, mixed> $context
     */
    public function __construct(
        null|string $message = null,
        null|array $context = null,
        null|false|Throwable $previous = null,
        int $code = LOG_LEVEL['critical'],
    ) {
        $previousThrowable = $this->resolvePrevious($previous);
        $this->errors      = [...ErrorBuffer::shared()->all()];
        $this->context     = Snapshot::context($context);

        $message ??= $previousThrowable?->getMessage() ?? 'Unspecified error';

        parent::__construct(
            message: $message,
            code: $code,
            previous: $previousThrowable,
        );
    }

    /**
     * @param null|array<string, mixed> $context
     */
    public static function from(
        Throwable $throwable,
        null|array $context = null,
    ): self {
        return new self(
            message: $throwable->getMessage(),
            context: $context,
            previous: $throwable->getPrevious(),
            code: $throwable->getCode(),
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
}

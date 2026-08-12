<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts\Exception\ExceptionContextSnapshot;

class OverflowException extends \OverflowException
{
    use ExceptionContextSnapshot;

    /**
     * @param null|array<array-key, mixed> $context
     */
    public function __construct(
        null|string     $message = null,
        null|array      $context = null,
        null|\Throwable $previous = null,
        int             $code = LOG_LEVEL['critical'],
    ) {
        $this->context = $context === null ? [] : Snapshot::context($context);

        $message = \trim($message ?? '');

        parent::__construct(
            message : empty($message) ? 'Unspecified ' . __CLASS__ : $message,
            code    : $code,
            previous: $previous,
        );
    }
}

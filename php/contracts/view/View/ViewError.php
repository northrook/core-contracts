<?php

declare(strict_types=1);

namespace Northrook\View;

use Northrook\ViewInterface;

/**
 * A renderable error view.
 *
 * Returnable as a {@see ViewInterface}.:
 */
class ViewError implements ViewInterface
{
    final public const int NOTICE  = 0;
    final public const int WARNING = 1;
    final public const int ERROR   = 2;
    final public const int FATAL   = 3;

    /**
     * The error that occurred, if any.
     *
     * @var null|\Throwable
     */
    final public private(set) null|\Throwable $error = null;

    /**
     * The severity level of the error.
     *
     * Higher numbers are more severe.
     *
     * @var int<0, 3>
     */
    final public private(set) int $level = ViewError::NOTICE;

    /**
     * Whether the action leading to this error can be retried.
     *
     * @var bool
     */
    final public private(set) bool $canRetry = false;

    public function render(): null|Toast
    {
        return new Toast($this->error?->getMessage() ?? 'Unknown ' . self::class);
    }
}

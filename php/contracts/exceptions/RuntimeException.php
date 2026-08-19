<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\ExceptionTrait;

class RuntimeException extends \RuntimeException implements ExceptionInterface
{
    use ExceptionTrait;

    /**
     * @param null|array<array-key, mixed> $context
     */
    public function __construct(
        null|string     $message = null,
        null|array      $context = null,
        null|\Throwable $previous = null,
        int             $code = 0,
    ) {
        parent::__construct(
            $this->_error_message($message, $previous),
            $this->_error_code($code),
            $previous,
        );
        $this->_context_snapshot($context);
    }

    /**
     * @param \Throwable                 $throwable
     * @param null|array<string, mixed>  $context
     *
     * @return \Northrook\RuntimeException
     */
    final public static function from(
        \Throwable $throwable,
        null|array $context = null,
    ): RuntimeException {
        [$message, $code, $context] = self::_resolve_throwable($throwable, $context);

        return new RuntimeException(
            message : $message,
            context : $context,
            previous: $throwable,
            code    : $code,
        );
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Autowire;

use Northrook\Contracts\Autowire;
use Psr\Log\LoggerInterface;

use const Northrook\Contracts\LOG_LEVEL;

/**
 * Autowires a PSR-3 logger and provides exception logging helpers.
 *
 * When no logger is bound, the assignment is skipped unless `$assignNull` is true.
 */
trait Logger
{
    protected null|LoggerInterface $logger = null;

    /**
     * @param null|LoggerInterface $logger
     * @param bool                 $assignNull
     *
     * @return void
     */
    final public function __autowireLogger(
        #[Autowire(LoggerInterface::class)]
        null|LoggerInterface $logger,
        bool                 $assignNull = false,
    ): void {
        if ($logger === null && $assignNull === false) {
            return;
        }

        $this->logger = $logger;
    }

    /**
     * @template T of \Throwable
     *
     * @param T                     $exception
     * @param null|string           $message
     * @param array<string, mixed>  $context
     * @param bool                  $continue
     *
     * @return void
     *
     * @throws ($continue is false ? T : never)
     */
    final protected function logException(
        \Throwable  $exception,
        null|string $message = null,
        array       $context = [],
        bool        $continue = false,
    ): void {
        $level = LOG_LEVEL[$exception->getCode()] ?? match (true) {
            $exception instanceof \RuntimeException, $exception instanceof \LogicException => 'critical',
            $exception instanceof \Exception => 'error',
            default => 'warning',
        };
        $message ??= $exception->getMessage();

        $context['exception'] = $exception;

        if (isset($this->logger)) {
            $this->logger->log(
                $level,
                $message,
                $context,
            );
        } else {
            \error_log(
                message: "{$level}: {$message}",
            );
        }

        if ($continue === true) {
            return;
        }

        throw $exception;
    }
}

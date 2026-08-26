<?php

declare(strict_types=1);

namespace Northrook\Autowire;

use Northrook\Container\Autowire;
use Northrook\Logger\LogLevel;
use Northrook\Logger\NativeLogger;
use Psr\Log\LoggerInterface;

/**
 * Autowires a PSR-3 logger and provides exception logging helpers.
 *
 * When no logger is bound, assignment is skipped — {@see $logger} is non-nullable
 * and the get hook falls back to {@see NativeLogger}. `$assignNull` is accepted
 * for the autowire signature but cannot store null on that property.
 */
trait Logger
{
    // TODO : use Context
    final protected private(set) LoggerInterface $logger {
        get => $this->logger ?? new NativeLogger;
        set => $this->logger = $value;
    }

    /**
     * @param null|LoggerInterface $logger
     * @param bool                 $assignNull
     *
     * @return void
     */
    final public function _autowire_Logger(
        #[Autowire(LoggerInterface::class)]
        null|LoggerInterface $logger,
        bool                 $assignNull = false,
    ): void {
        if ($logger === null) {
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
     * @throws T
     */
    final protected function logException(
        \Throwable  $exception,
        null|string $message = null,
        array       $context = [],
        bool        $continue = false,
    ): void {
        $level = LogLevel::tryFrom($exception->getCode()) ?? match (true) {
                $exception instanceof \RuntimeException, $exception instanceof \LogicException => LogLevel::CRITICAL,
                $exception instanceof \Exception => LogLevel::ERROR,
                default => LogLevel::WARNING,
            };

        $message ??= $exception->getMessage();

        $context['exception'] = $exception;

        $this->logger->log(
            $level->name(),
            $message,
            $context,
        );

        if ($continue === true) {
            return;
        }

        throw $exception;
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container\Autowire;

use Exception;
use LogicException;
use Northrook\Contracts\Container\Autowire;
use Psr\Log\{LoggerInterface, NullLogger};
use RuntimeException;
use Throwable;

use const Northrook\Logger\LOG_LEVEL;

/**
 * Autowires a PSR-3 logger and provides exception logging helpers.
 *
 * When no logger is bound, the assignment is skipped unless `$assignNull` is true, in which case a {@see NullLogger} is used.
 */
trait Logger
{
    protected LoggerInterface $logger;

    /**
     * @internal autowired by the {@see ContainerInterface}
     *
     * @param null|LoggerInterface $logger
     * @param bool                 $assignNull
     *
     * @return void
     * @final
     */
    #[Autowire]
    final public function assignLogger(
        null|LoggerInterface $logger,
        bool $assignNull = false,
    ): void {
        if ($logger === null && $assignNull === false) {
            return;
        }

        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param Throwable            $exception
     * @param null|string          $message
     * @param array<string, mixed> $context
     * @param bool                 $continue
     *
     * @return void
     *
     * @throws Throwable
     * @final
     */
    final protected function logException(
        Throwable $exception,
        null|string $message = null,
        array $context = [],
        bool $continue = false,
    ): void {
        $level = LOG_LEVEL[$exception->getCode()] ?? match (true) {
            $exception instanceof RuntimeException, $exception instanceof LogicException => 'critical',
            $exception instanceof Exception => 'error',
            default => 'warning',
        };
        $message ??= $exception->getMessage();

        $context['exception'] = $exception;

        $this->logger->log(
            $level,
            $message,
            $context,
        );

        if ($continue === true) {
            return;
        }

        throw $exception;
    }
}

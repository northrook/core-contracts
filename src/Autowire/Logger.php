<?php

declare(strict_types=1);

namespace Core\Contracts\Autowire;

use Core\Contracts\Container\Autowire;
use Psr\Log\{
    LoggerInterface,
    NullLogger
};
use JetBrains\PhpStorm\Language;
use Throwable;
use LogicException;
use RuntimeException;
use Exception;
use const Support\LOG_LEVEL;

trait Logger
{
    protected readonly LoggerInterface $logger;

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
        ?LoggerInterface $logger,
        bool             $assignNull = false,
    ) : void {
        if ( $logger === null && $assignNull === false ) {
            return;
        }

        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @template T of Throwable
     *
     * @param T                    $exception
     * @param null|string          $message
     * @param array<string, mixed> $context
     * @param bool                 $continue
     *
     * @return void
     *
     * @throws ($continue is false ? T : never)
     *
     * @final
     */
    final protected function logException(
        Throwable $exception,
        #[Language( 'Smarty' )]
        ?string   $message = null,
        array     $context = [],
        bool      $continue = false,
    ) : void {
        $level = LOG_LEVEL[$exception->getCode()] ?? match ( true ) {
            $exception instanceof RuntimeException,
            $exception instanceof LogicException => 'critical',
            $exception instanceof Exception      => 'error',
            default                              => 'warning',
        };
        $message ??= $exception->getMessage();

        $context['exception'] = $exception;

        $this->logger->log( $level, $message, $context );

        if ( $continue === true ) {
            return;
        }

        throw $exception;
    }
}

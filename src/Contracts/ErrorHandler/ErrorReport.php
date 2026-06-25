<?php

declare(strict_types=1);

namespace Northrook\Contracts\ErrorHandler;

use Exception;
use JsonSerializable;
use LogicException;
use Northrook\Contracts\Exceptions\CurlException;
use Northrook\Contracts\Exceptions\ErrorException;
use Northrook\Contracts\Exceptions\FilesystemException;
use RuntimeException;
use Throwable;

use const Northrook\Logger\LOG_LEVEL;

final class ErrorReport implements JsonSerializable
{
    /**
     * @param StackFrame[] $trace
     * @param ErrorReport[] $previous
     * @param array<string, mixed> $context
     * @param array<string, mixed> $dumps
     * @param null|array{type: int, message: string, file: string, line: int} $phpError
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $reference,
        public readonly float $timestamp,
        public readonly string $severity,
        public readonly string $class,
        public readonly string $message,
        public readonly int $code,
        public readonly string $file,
        public readonly int $line,
        public readonly array $trace,
        public readonly array $previous = [],
        public readonly array $context = [],
        public readonly array $dumps = [],
        public readonly null|array $phpError = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $dumps
     */
    public static function from(
        Throwable $throwable,
        array $context = [],
        array $dumps = [],
    ): self {
        $reference = 'error-' . \hash('xxh32', \spl_object_id($throwable) . $throwable->getMessage());

        return self::fromThrowable(
            $throwable,
            $reference,
            $context ?: self::buildDefaultContext(),
            $dumps,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $dumps
     */
    private static function fromThrowable(
        Throwable $throwable,
        string $reference,
        array $context,
        array $dumps,
    ): self {
        return new self(
            reference: $reference,
            timestamp: \microtime(true),
            severity: self::resolveSeverity($throwable),
            class: $throwable::class,
            message: $throwable->getMessage(),
            code: (int) $throwable->getCode(),
            file: $throwable->getFile(),
            line: $throwable->getLine(),
            trace: self::buildTrace($throwable),
            previous: self::buildPreviousChain($throwable->getPrevious()),
            context: $context,
            dumps: $dumps,
            phpError: $throwable instanceof ErrorException ? $throwable->error : null,
            meta: self::buildMeta($throwable),
        );
    }

    /**
     * @return StackFrame[]
     */
    private static function buildTrace(Throwable $throwable): array
    {
        $frames = [];

        /**
         * @var array{
         *      file?: string,
         *      line?: int,
         *      function?: string,
         *      class?: class-string,
         *      type?: string,
         *      args?: array<string, mixed>,
         *  } $frame
         */
        foreach ($throwable->getTrace() as $frame) {
            $frames[] = StackFrame::from($frame);
        }

        $frames[] = StackFrame::from([
            'file'     => $throwable->getFile(),
            'line'     => $throwable->getLine(),
            'function' => '{main}',
        ]);

        return $frames;
    }

    /**
     * @return ErrorReport[]
     */
    private static function buildPreviousChain(null|Throwable $previous): array
    {
        if ($previous === null) {
            return [];
        }

        $reference = 'error-' . \hash('xxh32', \spl_object_id($previous) . $previous->getMessage());

        return [
            self::fromThrowable(
                $previous,
                $reference,
                [],
                [],
            ),
        ];
    }

    private static function resolveSeverity(Throwable $throwable): string
    {
        $code = $throwable->getCode();

        if (\is_string(LOG_LEVEL[$code] ?? null)) {
            return LOG_LEVEL[$code];
        }

        return match (true) {
            $throwable instanceof RuntimeException, $throwable instanceof LogicException => 'critical',
            $throwable instanceof Exception => 'error',
            default => 'warning',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildDefaultContext(): array
    {
        $context = [
            'sapi'   => PHP_SAPI,
            'cwd'    => \getcwd() ?: '',
            'memory' => \memory_get_usage(true),
        ];

        if (PHP_SAPI !== 'cli' && ! \defined('STDIN')) {
            $context['request'] = [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'uri'    => $_SERVER['REQUEST_URI'] ?? '',
                'host'   => $_SERVER['HTTP_HOST'] ?? '',
            ];
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildMeta(Throwable $throwable): array
    {
        return match (true) {
            $throwable instanceof CurlException => ['url' => $throwable->url],
            $throwable instanceof FilesystemException => \array_filter([
                'path' => $throwable->getPath(),
            ]),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'reference' => $this->reference,
            'timestamp' => $this->timestamp,
            'severity'  => $this->severity,
            'throwable' => [
                'class'   => $this->class,
                'message' => $this->message,
                'code'    => $this->code,
                'file'    => $this->file,
                'line'    => $this->line,
                'meta'    => $this->meta,
            ],
            'phpError'  => $this->phpError,
            'trace'     => $this->trace,
            'previous'  => $this->previous,
            'context'   => $this->context,
            'dumps'     => $this->dumps,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\ErrorHandler;

use Exception;
use JsonSerializable;
use LogicException;
use Northrook\Contracts\ContextSnapshot;
use Northrook\Contracts\Exceptions\CurlException;
use Northrook\Contracts\Exceptions\ErrorException;
use Northrook\Contracts\Exceptions\FilesystemException;
use Northrook\Contracts\Exceptions\RuntimeException as ContractsRuntimeException;
use Northrook\Contracts\Interfaces\ErrorBufferInterface;
use RuntimeException;
use Throwable;

use const Northrook\Logger\LOG_LEVEL;

/**
 * @phpstan-import-type ErrorArray from RuntimeError
 */
final class ErrorReport implements JsonSerializable
{
    /**
     * @param StackFrame[] $trace
     * @param ErrorReport[] $previous
     * @param array<string, mixed> $context
     * @param array<string, mixed> $dumps
     * @param null|ErrorArray $phpError
     * @param list<ErrorArray> $phpErrors
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
        public readonly array $phpErrors = [],
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
        null|ErrorBufferInterface $buffer = null,
    ): self {
        $reference = 'error-' . \hash('xxh32', \spl_object_id($throwable) . $throwable->getMessage());
        $phpErrors = self::resolvePhpErrors($throwable, $buffer);

        return self::fromThrowable(
            $throwable,
            $reference,
            \array_merge(
                $context ?: self::buildDefaultContext(),
                self::exceptionContext($throwable),
            ),
            $dumps,
            $phpErrors,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $dumps
     * @param list<ErrorArray> $phpErrors
     */
    private static function fromThrowable(
        Throwable $throwable,
        string $reference,
        array $context,
        array $dumps,
        array $phpErrors,
    ): self {
        return new self(
            reference: $reference,
            timestamp: \microtime(true),
            severity: self::resolveSeverity($throwable),
            class: $throwable::class,
            message: $throwable->getMessage(),
            code: $throwable->getCode(),
            file: $throwable->getFile(),
            line: $throwable->getLine(),
            trace: self::buildTrace($throwable),
            previous: self::buildPreviousChain($throwable->getPrevious()),
            context: $context,
            dumps: $dumps,
            phpError: $phpErrors[0] ?? self::resolveLegacyPhpError($throwable),
            phpErrors: $phpErrors,
            meta: self::buildMeta($throwable),
        );
    }

    /**
     * @return StackFrame[]
     */
    private static function buildTrace(
        Throwable $throwable,
    ): array {
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
    private static function buildPreviousChain(
        null|Throwable $previous,
    ): array {
        if ($previous === null) {
            return [];
        }

        $reference = 'error-' . \hash('xxh32', \spl_object_id($previous) . $previous->getMessage());

        return [
            self::fromThrowable(
                $previous,
                $reference,
                self::exceptionContext($previous),
                [],
                self::resolvePhpErrors($previous),
            ),
        ];
    }

    private static function resolveSeverity(
        Throwable $throwable,
    ): string {
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
    private static function exceptionContext(
        Throwable $throwable,
    ): array {
        if (! $throwable instanceof ContractsRuntimeException) {
            return [];
        }

        $exported = [];

        foreach ($throwable->context as $key => $value) {
            $exported[$key] = $value instanceof ContextSnapshot
                ? $value->jsonSerialize()
                : $value;
        }

        return $exported;
    }

    /**
     * @return list<ErrorArray>
     */
    private static function resolvePhpErrors(
        Throwable $throwable,
        null|ErrorBufferInterface $buffer = null,
    ): array {
        if ($throwable instanceof ContractsRuntimeException) {
            $fromContext = self::exportPhpErrorsFromContext($throwable->context['phpErrors'] ?? null);

            if ($fromContext !== []) {
                return $fromContext;
            }
        }

        if ($throwable instanceof ErrorException) {
            return [$throwable->error];
        }

        if ($throwable instanceof ContractsRuntimeException) {
            $legacy = self::exportPhpErrorValue($throwable->context['phpError'] ?? null);

            if ($legacy !== null) {
                return [$legacy];
            }
        }

        if ($buffer !== null && $buffer->count() > 0) {
            return self::exportRuntimeErrors($buffer->all());
        }

        return [];
    }

    /**
     * @return null|ErrorArray
     */
    private static function resolveLegacyPhpError(
        Throwable $throwable,
    ): null|array {
        if ($throwable instanceof ErrorException) {
            return $throwable->error;
        }

        if (! $throwable instanceof ContractsRuntimeException) {
            return null;
        }

        return self::exportPhpErrorValue($throwable->context['phpError'] ?? null);
    }

    /**
     * @return list<ErrorArray>
     */
    private static function exportPhpErrorsFromContext(
        mixed $value,
    ): array {
        if ($value === null) {
            return [];
        }

        if (\is_array($value)) {
            return self::exportPhpErrorList($value);
        }

        if ($value instanceof ContextSnapshot) {
            if (\is_array($value->value)) {
                return self::exportPhpErrorList($value->value);
            }

            $single = self::exportPhpErrorValue($value->value);

            return $single !== null ? [$single] : [];
        }

        $single = self::exportPhpErrorValue($value);

        return $single !== null ? [$single] : [];
    }

    /**
     * @param list<RuntimeError> $errors
     *
     * @return list<ErrorArray>
     */
    private static function exportRuntimeErrors(
        array $errors,
    ): array {
        return \array_map(
            static fn(RuntimeError $error): array => $error->toArray(),
            $errors,
        );
    }

    /**
     * @param array<mixed> $errors
     *
     * @return list<ErrorArray>
     */
    private static function exportPhpErrorList(
        array $errors,
    ): array {
        if ($errors === []) {
            return [];
        }

        if (self::isErrorArray($errors)) {
            return [$errors];
        }

        $exported = [];

        foreach ($errors as $error) {
            $array = self::exportPhpErrorValue($error);

            if ($array !== null) {
                $exported[] = $array;
            }
        }

        return $exported;
    }

    /**
     * @return null|ErrorArray
     */
    private static function exportPhpErrorValue(
        mixed $value,
    ): null|array {
        if ($value instanceof RuntimeError) {
            return $value->toArray();
        }

        if ($value instanceof ContextSnapshot) {
            if ($value->value instanceof RuntimeError) {
                return $value->value->toArray();
            }

            if (\is_array($value->value) && self::isErrorArray($value->value)) {
                return $value->value;
            }

            return null;
        }

        if (\is_array($value) && self::isErrorArray($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param array<mixed> $array
     */
    private static function isErrorArray(
        array $array,
    ): bool {
        return (
            isset($array['type'], $array['message'], $array['file'], $array['line'])
            && \is_int($array['type'])
            && \is_string($array['message'])
            && \is_string($array['file'])
            && \is_int($array['line'])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildMeta(
        Throwable $throwable,
    ): array {
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
            'phpErrors' => $this->phpErrors,
            'trace'     => $this->trace,
            'previous'  => $this->previous,
            'context'   => $this->context,
            'dumps'     => $this->dumps,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Logger;

use Northrook\Context;
use Psr\Log\AbstractLogger;

/**
 * Lightweight PSR-3 logger.
 *
 * Writes to an optional log file (or a dated file under a directory). When no
 * path is given — or file append fails — falls back to {@see \error_log()}.
 *
 * Intended as the default outside a Kernel/container. Inject a dedicated
 * logger when structured records, handlers, or persistent storage are needed.
 */
final class NativeLogger extends AbstractLogger
{
    /**
     * Resolved absolute-ish log file path, or null for {@see \error_log()}.
     */
    private readonly null|string $logFile;

    /** @var list<non-empty-string> */
    public private(set) array $entries = [];

    /**
     * @param null|string $path Log file to append, or directory that receives `Y-m-d.log`
     */
    public function __construct(
        null|string $path = null,
    ) {
        $this->logFile = self::resolveLogFile($path);
    }

    public function log(
        mixed              $level,
        string|\Stringable $message,
        array              $context = [],
    ): void {
        if (Context::isTesting()) {
            return;
        }

        if ($message instanceof \Throwable) {
            $context['exception'] ??= $message;
            $message              = $message->getMessage();
        }

        $label     = LogLevel::resolve($level)->label();
        $message   = self::interpolate(\string($message), $context);
        $exception = $context['exception'] ?? null;
        $timestamp = new \DateTimeImmutable()->format('Y-m-d H:i:s v');

        if ($exception instanceof \Throwable) {
            $message .= \PHP_EOL . $exception;
        }

        $line = "{$timestamp} [{$label}] {$message}";

        $this->entries[] = $line;

        if ($this->logFile !== null && $this->appendToFile($line)) {
            return;
        }

        \error_log($line);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function interpolate(
        string $message,
        array  $context,
    ): string {
        $replace = [];

        foreach ($context as $key => $value) {
            if ($value === null) {
                $replace['{' . $key . '}'] = '';
            }
            elseif (\is_scalar($value) || $value instanceof \Stringable) {
                $replace['{' . $key . '}'] = \string($value);
            }
        }

        return \strtr($message, $replace);
    }

    private static function resolveLogFile(
        null|string $path,
    ): null|string {
        if ($path === null || $path === '') {
            return null;
        }

        if (\is_dir($path)) {
            return \rtrim($path, '/\\') . \DIRECTORY_SEPARATOR . \date('Y-m-d') . '.log';
        }

        return $path;
    }

    private function appendToFile(
        string $line,
    ): bool {
        $directory = \dirname((string) $this->logFile);

        if ($directory !== '' && $directory !== '.' && ! \is_dir($directory)) {
            if (! @\mkdir($directory, 0777, true) && ! \is_dir($directory)) {
                return false;
            }
        }

        return (
            @\file_put_contents(
                (string) $this->logFile,
                $line . \PHP_EOL,
                \FILE_APPEND | \LOCK_EX,
            ) !== false
        );
    }
}

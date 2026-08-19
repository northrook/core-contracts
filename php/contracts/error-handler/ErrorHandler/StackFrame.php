<?php

declare(strict_types=1);

namespace Northrook\ErrorHandler;

use Northrook\DataObject;

final readonly class StackFrame extends DataObject
{
    /**
     * @param array<int, string>       $code
     * @param array<int|string, mixed> $args
     */
    public function __construct(
        public null|string $file,
        public null|int    $line,
        public null|string $function,
        public null|string $class,
        public null|string $type,
        public array       $args = [],
        public array       $code = [],
    ) {
        parent::__construct();
    }

    /**
     * @param array{
     *     file?: string,
     *     line?: int,
     *     function?: string,
     *     class?: class-string,
     *     type?: string,
     *     args?: array<int|string, mixed>,
     * }|\Throwable $trace
     */
    public static function from(
        \Throwable|array $trace,
        int              $codeRadius = 3,
    ): self {
        if ($trace instanceof \Throwable) {
            $frame = $trace->getTrace()[0] ?? [];

            $trace = [
                'file'     => $trace->getFile() !== '' ? $trace->getFile() : $frame['file'] ?? null,
                'line'     => $trace->getLine(),
                'function' => $frame['function'] ?? null,
                'class'    => $frame['class'] ?? null,
                'type'     => $frame['type'] ?? null,
                'args'     => $frame['args'] ?? [],
            ];
        }

        $file     = $trace['file'] ?? null;
        $line     = $trace['line'] ?? null;
        $function = $trace['function'] ?? null;
        $class    = $trace['class'] ?? null;
        $type     = $trace['type'] ?? null;
        $args     = $trace['args'] ?? [];
        $code     = [];

        if (\is_string($file) && \is_int($line) && \is_readable($file)) {
            $code = self::readCodeSnippet($file, $line, $codeRadius);
        }

        return new self($file, $line, $function, $class, $type, $args, $code);
    }

    /**
     * @return array<int, string>
     */
    private static function readCodeSnippet(
        string $file,
        int    $line,
        int    $radius,
    ): array {
        $lines   = \file($file, FILE_IGNORE_NEW_LINES);
        $snippet = [];

        if ($lines === false) {
            return $snippet;
        }

        $start = \max(0, $line - $radius - 1);
        $end   = \min(\count($lines), $line + $radius);

        for ($i = $start; $i < $end; $i++) {
            $snippet[$i + 1] = $lines[$i];
        }

        return $snippet;
    }
}

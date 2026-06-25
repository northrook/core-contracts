<?php

declare(strict_types=1);

namespace Northrook\Contracts\ErrorHandler;

use JsonSerializable;

final readonly class StackFrame implements JsonSerializable
{
    /**
     * @param array<int, string> $code
     * @param array<string, mixed> $args
     */
    public function __construct(
        public null|string $file,
        public null|int $line,
        public null|string $function,
        public null|string $class,
        public null|string $type,
        public array $args = [],
        public array $code = [],
    ) {}

    /**
     * @param array{
     *     file?: string,
     *     line?: int,
     *     function?: string,
     *     class?: class-string,
     *     type?: string,
     *     args?: array<string, mixed>,
     * } $trace
     */
    public static function from(array $trace, int $codeRadius = 3): self
    {
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
    private static function readCodeSnippet(string $file, int $line, int $radius): array
    {
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

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'file'     => $this->file,
            'line'     => $this->line,
            'function' => $this->function,
            'class'    => $this->class,
            'type'     => $this->type,
            'args'     => $this->args,
            'code'     => $this->code,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\ErrorHandler;

use Northrook\Contracts\DataObject;

/**
 * @used-by ErrorReport
 */
final readonly class ErrorSnapshot extends DataObject
{
    public StackFrame $stackFrame;

    /**
     * @param array<string, mixed> $meta
     */
    private function __construct(
        public string $class,
        public string $message,
        public int $code,
        public string $file,
        public int $line,
        public array $meta = [],
    ) {
        $this->stackFrame = new StackFrame(
            file: $this->file,
            line: $this->line,
            function: null,
            class: $this->class,
            type: null,
        );
        parent::__construct();
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function from(
        string $class,
        string $message,
        int $code,
        string $file,
        int $line,
        array $meta = [],
    ): ErrorSnapshot {
        return new self($class, $message, $code, $file, $line, $meta);
    }
}

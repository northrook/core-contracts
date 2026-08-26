<?php

declare(strict_types=1);

namespace Northrook\Debug;

use Northrook\ErrorHandler\StackFrame;

/**
 * Cursor over a {@see \debug_backtrace()} stack with lazy {@see StackFrame} materialization.
 *
 * Index `0` after {@see capture()} is the immediate caller. {@see up()} / {@see down()} /
 * {@see at()} return new instances sharing the same raw stack.
 *
 * @implements \IteratorAggregate<int, StackFrame>
 */
final class Backtrace implements \Countable, \IteratorAggregate
{
    /** @var list<array<string, mixed>> */
    private array $stack;

    private int $index;

    /** @var array<int, StackFrame> */
    private array $frames = [];

    /**
     * @param list<array<string, mixed>> $stack
     */
    private function __construct(
        array $stack,
        int   $index = 0,
    ) {
        $this->stack = $stack;
        $this->index = $this->clamp($index);
    }

    /**
     * Capture the current call stack.
     *
     * Drops the {@see capture()} frame so index `0` is the caller.
     *
     * @param int $limit   Max frames for {@see \debug_backtrace()}; `0` = no limit
     * @param int $options {@see \DEBUG_BACKTRACE_IGNORE_ARGS} by default
     */
    public static function capture(
        int $limit = 0,
        int $options = \DEBUG_BACKTRACE_IGNORE_ARGS,
    ): self {
        $limit = $limit > 0 ? $limit + 1 : 0;
        $stack = \debug_backtrace($options, $limit);
        $self  = \array_shift($stack);

        // file/line on a frame is the call site of that function. After dropping
        // capture(), graft its call-site onto the caller frame so index 0 is both
        // the calling function and the location of the capture() call.
        if ($stack !== [] && \is_array($self) && isset($self['file'], $self['line'])) {
            $stack[0]['file'] = $self['file'];
            $stack[0]['line'] = $self['line'];
        }

        return new self($stack);
    }

    /**
     * Wrap an existing trace array or a throwable's {@see \Throwable::getTrace()}.
     *
     * @param list<array<string, mixed>>|\Throwable $trace
     */
    public static function from(
        array|\Throwable $trace,
    ): self {
        if ($trace instanceof \Throwable) {
            $trace = $trace->getTrace();
        }

        return new self($trace);
    }

    public function index(): int
    {
        return $this->index;
    }

    public function count(): int
    {
        return \count($this->stack);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function raw(): array
    {
        return $this->stack;
    }

    /**
     * Frame at `$at`, or at the cursor when `$at` is null.
     */
    public function frame(
        null|int $at = null,
    ): null|StackFrame {
        $at ??= $this->index;

        if (! isset($this->stack[$at])) {
            return null;
        }

        return $this->frames[$at] ??= $this->hydrate($this->stack[$at]);
    }

    /**
     * New cursor `$steps` frames toward the outer caller (higher index).
     */
    public function up(
        int $steps = 1,
    ): self {
        return $this->at($this->index + \max(0, $steps));
    }

    /**
     * New cursor `$steps` frames toward the inner callee (lower index).
     */
    public function down(
        int $steps = 1,
    ): self {
        return $this->at($this->index - \max(0, $steps));
    }

    /**
     * Jump to `$index` (clamped to the stack bounds).
     */
    public function at(
        int $index,
    ): self {
        return \clone($this, [
            'index' => $this->clamp($index),
        ]);
    }

    /**
     * First frame from the cursor with file+line that is not skipped.
     *
     * Default skip: {@see self} and this file's path.
     *
     * @param null|string|list<string> $skip Class-strings, namespace prefixes (`Foo\`), or path prefixes
     */
    public function source(
        null|string|array $skip = null,
    ): null|StackFrame {
        $skip = $this->normalizeSkip($skip);

        for ($i = $this->index, $n = \count($this->stack); $i < $n; $i++) {
            $raw = $this->stack[$i];

            if (! isset($raw['file'], $raw['line'])) {
                continue;
            }

            if ($this->isSkipped($raw, $skip)) {
                continue;
            }

            return $this->frame($i);
        }

        return null;
    }

    /**
     * First frame from the cursor whose class, function, or `Class::function` equals `$match`.
     */
    public function find(
        string $match,
    ): null|StackFrame {
        for ($i = $this->index, $n = \count($this->stack); $i < $n; $i++) {
            $raw      = $this->stack[$i];
            $function = isset($raw['function']) && \is_string($raw['function']) ? $raw['function'] : null;
            $class    = isset($raw['class']) && \is_string($raw['class']) ? $raw['class'] : null;

            if ($function === $match || $class === $match) {
                return $this->frame($i);
            }

            if ($class !== null && $function !== null && $class . '::' . $function === $match) {
                return $this->frame($i);
            }
        }

        return null;
    }

    /**
     * @return \Traversable<int, StackFrame>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->stack as $i => $_) {
            $frame = $this->frame($i);
            if ($frame !== null) {
                yield $i => $frame;
            }
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function hydrate(
        array $raw,
    ): StackFrame {
        return new StackFrame(
            file    : isset($raw['file']) && \is_string($raw['file']) ? $raw['file'] : null,
            line    : isset($raw['line']) && \is_int($raw['line']) ? $raw['line'] : null,
            function: isset($raw['function']) && \is_string($raw['function']) ? $raw['function'] : null,
            class   : isset($raw['class']) && \is_string($raw['class']) ? $raw['class'] : null,
            type    : isset($raw['type']) && \is_string($raw['type']) ? $raw['type'] : null,
            args    : isset($raw['args']) && \is_array($raw['args']) ? $raw['args'] : [],
        );
    }

    private function clamp(
        int $index,
    ): int {
        $count = \count($this->stack);

        if ($count === 0) {
            return 0;
        }

        return \max(0, \min($index, $count - 1));
    }

    /**
     * @param null|string|list<string> $skip
     *
     * @return list<string>
     */
    private function normalizeSkip(
        null|string|array $skip,
    ): array {
        $defaults = [
            self::class,
            __FILE__,
        ];

        if ($skip === null) {
            return $defaults;
        }

        if (\is_string($skip)) {
            $skip = [$skip];
        }

        return [...$defaults, ...$skip];
    }

    /**
     * @param array<string, mixed> $raw
     * @param list<string>         $skip
     */
    private function isSkipped(
        array $raw,
        array $skip,
    ): bool {
        $file  = isset($raw['file']) && \is_string($raw['file']) ? $raw['file'] : null;
        $class = isset($raw['class']) && \is_string($raw['class']) ? $raw['class'] : null;

        foreach ($skip as $rule) {
            if ($rule === '') {
                continue;
            }

            if ($class !== null) {
                if ($class === $rule || \str_starts_with($class, $rule)) {
                    return true;
                }
            }

            if ($file !== null && ( $file === $rule || \str_starts_with($file, $rule) )) {
                return true;
            }
        }

        return false;
    }
}

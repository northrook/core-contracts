<?php

declare(strict_types=1);

namespace Northrook\Contracts\ErrorHandler;

use Northrook\Contracts\Interfaces\ErrorBufferInterface;

/**
 * In-process buffer of PHP engine errors for debugging and error reports.
 *
 * @phpstan-import-type ErrorArray from RuntimeError
 */
final class ErrorBuffer implements ErrorBufferInterface
{
    private static null|self $shared = null;

    /** @var list<RuntimeError> */
    private array $errors = [];

    public static function shared(): self
    {
        return self::$shared ??= new self();
    }

    public static function setShared(
        null|self $buffer,
    ): void {
        self::$shared = $buffer;
    }

    public function record(
        RuntimeError $error,
    ): void {
        $this->errors[] = $error;
    }

    public function recordFrom(
        int $type,
        string $message,
        string $file,
        int $line,
    ): void {
        $this->record(RuntimeError::from([
            'type'    => $type,
            'message' => $message,
            'file'    => $file,
            'line'    => $line,
        ]));
    }

    /**
     * @return list<RuntimeError>
     */
    public function all(): array
    {
        return $this->errors;
    }

    public function last(): null|RuntimeError
    {
        if ($this->errors === []) {
            return null;
        }

        return $this->errors[\array_key_last($this->errors)];
    }

    public function count(): int
    {
        return \count($this->errors);
    }

    public function mark(): int
    {
        return \count($this->errors);
    }

    /**
     * @return list<RuntimeError>
     */
    public function since(
        int $mark,
    ): array {
        if ($mark < 0) {
            $mark = 0;
        }

        return \array_slice($this->errors, $mark);
    }

    /**
     * @return list<ErrorArray>
     */
    public function sinceArrays(
        int $mark,
    ): array {
        return \array_map(
            static fn(RuntimeError $error): array => $error->toArray(),
            $this->since($mark),
        );
    }

    public function reset(): self
    {
        $this->errors = [];

        return $this;
    }
}

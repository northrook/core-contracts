<?php

declare(strict_types=1);

namespace Northrook\Contracts\ErrorHandler;

use JsonSerializable;
use Northrook\Contracts\Exceptions\RuntimeException;
use Stringable;

/**
 * Typed value object for a PHP engine errors.
 *
 * Carries the same `type`, `message`, `file`, and `line` keys as {@see error_get_last()}.
 *
 * @phpstan-type ErrorArray array{
 *     type: int,
 *     message: string,
 *     file: string,
 *     line: int,
 * }
 */
final readonly class RuntimeError implements Stringable, JsonSerializable
{
    private function __construct(
        public int $type,
        public string $message,
        public string $file,
        public int $line,
    ) {}

    /**
     * @returns RuntimeError from {@see error_get_last()} or `null` if there is no last error
     */
    public static function fromLast(): null|self
    {
        $error = error_get_last();

        return $error !== null
            ? new self(...self::validate($error))
            : null;
    }

    /**
     * @param array<string, mixed> $array
     */
    public static function from(
        array $array,
    ): self {
        return new self(...self::validate($array));
    }

    /**
     * @return ErrorArray
     */
    public function toArray(): array
    {
        return $this->__serialize();
    }

    /**
     * @return string `file:line: message`
     */
    public function __toString(): string
    {
        return "{$this->file}:{$this->line}: " . \trim($this->message);
    }

    /** @return ErrorArray */
    public function jsonSerialize(): array
    {
        return $this->__serialize();
    }

    /** @return ErrorArray */
    public function __serialize(): array
    {
        return [
            'type'    => $this->type,
            'message' => $this->message,
            'file'    => $this->file,
            'line'    => $this->line,
        ];
    }

    /**
     * @param ErrorArray $data
     */
    public function __unserialize(
        array $data,
    ): void {
        $error = self::validate($data);

        $this->type    = $error['type'];
        $this->message = $error['message'];
        $this->file    = $error['file'];
        $this->line    = $error['line'];
    }

    /**
     * @param array<string, mixed> $array
     *
     * @return ErrorArray
     *
     * @throws RuntimeException when required keys are missing or wrong types
     */
    private static function validate(
        array $array,
    ): array {
        if (
            isset($array['type'], $array['message'], $array['file'], $array['line'])
            && \is_int($array['type'])
            && \is_string($array['message'])
            && \is_string($array['file'])
            && \is_int($array['line'])
        ) {
            return [
                'type'    => $array['type'],
                'message' => $array['message'],
                'file'    => $array['file'],
                'line'    => $array['line'],
            ];
        }

        throw new RuntimeException(
            message: 'Invalid error array format.',
            context: ['$array' => $array],
            previous: false,
        );
    }
}

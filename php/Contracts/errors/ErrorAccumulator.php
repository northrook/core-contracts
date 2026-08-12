<?php

declare(strict_types=1);

namespace Northrook\Contracts;

final class ErrorAccumulator implements \Countable, \Stringable
{
    /**
     * @var array<non-empty-lowercase-string, ErrorAccumulator>
     */
    private static array $accumulators = [];

    /**
     * @var array<int|string, \Throwable>
     */
    private array $errors = [];

    public readonly string $reference;

    /**
     * Registers this instance under `$reference`.
     *
     * Throws if that key is already taken — resume via {@see get()}, {@see register()},
     * or {@see clear()} first.
     *
     * @param non-empty-lowercase-string $reference
     */
    public function __construct(
        string                       $reference,
        private readonly null|string $message = null,
    ) {
        $this->reference = self::key($reference);

        if (isset(self::$accumulators[$this->reference])) {
            throw new InvalidArgumentException(
                message : self::class . " reference '{$this->reference}' is already registered",
                name    : 'reference',
                expected: 'unused accumulator key',
                received: $this->reference,
            );
        }

        self::$accumulators[$this->reference] = $this;
    }

    public function count(): int
    {
        return \count($this->errors);
    }

    public function __toString(): string
    {
        return \implode(
            separator: "\n",
            array    : $this->errors,
        );
    }

    public function hasErrors(): bool
    {
        return $this->count() > 0;
    }

    /**
     * @return array<int|string, \Throwable>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function add(
        \Throwable  $throwable,
        null|string $note = null,
    ): ErrorAccumulator {
        if ($note !== null) {
            $index                = \count($this->errors) . ' ' . $note;
            $this->errors[$index] = $throwable;
        } else {
            $this->errors[] = $throwable;
        }

        return $this;
    }

    public static function register(
        string      $reference,
        \Throwable  $throwable,
        null|string $note = null,
    ): ErrorAccumulator {
        $reference   = self::key($reference);
        $accumulator = self::$accumulators[$reference] ?? new self($reference);

        return $accumulator->add($throwable, $note);
    }

    public static function get(
        string $reference,
    ): null|ErrorAccumulator {
        return self::$accumulators[self::key($reference)] ?? null;
    }

    public static function check(
        string      $reference,
        null|string $message = null,
    ): false {
        $accumulator = self::get($reference);
        if ($accumulator === null || ! $accumulator->hasErrors()) {
            return false;
        }

        $message ??= $accumulator->message ?? "ErrorAccumulator: $reference encountered {$accumulator->count()} errors:";

        foreach ($accumulator->getErrors() as $error) {
            $message .= "\n" . $error->getMessage();
        }

        throw new RuntimeException(
            message: $message,
            context: [
                'reference' => $reference,
                'errors'    => $accumulator->getErrors(),
            ],
        );
    }

    public static function clear(
        string $reference,
    ): void {
        if ($reference === '*') {
            self::$accumulators = [];
        } else {
            unset(self::$accumulators[self::key($reference)]);
        }
    }

    /**
     * @param string $from
     *
     * @return non-empty-lowercase-string
     */
    private static function key(
        string $from,
    ): string {
        $string = \trim($from);

        if ($string !== '') {
            return \strtolower($string);
        }

        throw new InvalidArgumentException(
            message: self::class . ' requires a non-empty reference key string',
            context: ['from' => $from],
        );
    }
}

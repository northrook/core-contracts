<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\ErrorHandler\ErrorBuffer;
use Northrook\ExceptionInterface;
use Northrook\Snapshot;

/**
 * @phpstan-require-extends \Exception
 * @phpstan-require-implements \Northrook\ExceptionInterface
 */
trait ExceptionTrait
{
    /**
     * @var array<array-key, mixed>
     */
    protected private(set) readonly array $context;

    /**
     * @return array<array-key, mixed>
     */
    final public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return list<\Northrook\ErrorHandler\RuntimeError>
     */
    final public function getErrors(): array
    {
        /** @var list<\Northrook\ErrorHandler\RuntimeError> $errors */
        $errors = $this->context['errors'] ?? [];

        if (\is_array($errors)) {
            return $errors;
        }

        throw new \RuntimeException('Invalid errors context');
    }

    /**
     * @initializer
     *
     * @param null|array<array-key,mixed> $context
     *
     * @return void
     */
    final protected function _context_snapshot(
        null|array $context = null,
    ): void {
        $context ??= [];

        $previous = $this->getPrevious();

        if ($previous !== null) {
            $context['previous'] = $previous;
        }

        $context['errors'] ??= ErrorBuffer::snapshot();

        $this->context = Snapshot::context($context);
    }

    /**
     * @param \Throwable $throwable
     * @param null|array<array-key,mixed> $context
     *
     * @return array{
     *     0: string,
     *     1: int,
     *     2: array<array-key, mixed>,
     * }
     */
    final protected static function _resolve_throwable(
        \Throwable $throwable,
        null|array $context = null,
    ): array {
        return [
            self::_error_message($throwable->getMessage(), $throwable->getPrevious()),
            self::_error_code($throwable),
            $throwable instanceof ExceptionInterface
                ? [...$throwable->getContext(), ...( $context ?? [] )]
                : $context ?? [],
        ];
    }

    final protected static function _error_message(
        mixed           $from,
        null|\Throwable $previous,
    ): string {
        $message = $from;

        if ($message === null) {
            if ($previous !== null && \trim($previous->getMessage()) !== '') {
                $message = $previous->getMessage();
            } elseif ($previous !== null) {
                $fallback = \array_last(\explode('\\', $previous::class));

                if (! \is_string($fallback) || $fallback === '') {
                    $fallback = 'Unknown Exception';
                }

                $message = $fallback;
            } else {
                $message = 'Unspecified error';
            }
        }

        $message = \trim(match (true) {
            \is_scalar($message) => \is_bool($message) ? ( $message ? 'true' : 'false' ) : (string) $message,
            $message instanceof \Stringable => $message->__toString(),
            $message instanceof \BackedEnum => \strval($message->value),
            default => '',
        });

        if (empty($message)) {
            return 'Unspecified error';
        }

        return $message;
    }

    /**
     * @param int|string|\Throwable|\BackedEnum  $from
     *
     * @return int
     */
    final protected static function _error_code(
        int|string|\Throwable|\BackedEnum $from,
    ): int {
        /**
         * @var int|string $value
         */
        $value = match (true) {
            $from instanceof \Throwable => $from->getCode(),
            $from instanceof \BackedEnum => $from->value,
            default => $from,
        };

        return \is_int($value)
            ? $value
            : 0;
    }
}

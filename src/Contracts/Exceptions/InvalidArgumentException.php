<?php

declare(strict_types=1);

namespace Northrook\Contracts\Exceptions;

final class InvalidArgumentException extends RuntimeException
{
    public function __construct(
        null|string $name = null,
        mixed $expected = null,
        mixed $received = null,
        null|string $message = null,
        null|array $context = null,
        null|false|\Throwable $previous = null,
    ) {
        $context             ??= [];
        $context['name']     = $name;
        $context['expected'] = $expected;
        $context['received'] = $received;

        if ($message === null) {
            $message = 'Invalid argument';

            if ($context['name'] !== null) {
                $message .= " '{$context['name']}'";
            }

            if ($context['expected'] !== null) {
                $message .= " expected to be of type '" . \get_debug_type($context['expected']) . "'";
            }
            if ($context['received'] !== null) {
                $message .= ", received '" . \get_debug_type($context['received']) . "'";
            }
        }

        parent::__construct(
            message: $message,
            context: $context,
            previous: $previous,
        );
    }
}

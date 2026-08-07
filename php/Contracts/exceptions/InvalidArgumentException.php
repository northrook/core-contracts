<?php

declare(strict_types=1);

namespace Northrook\Contracts;

class InvalidArgumentException extends RuntimeException
{
    public function __construct(
        null|string           $message = null,
        null|string           $name = null,
        mixed                 $expected = null,
        mixed                 $received = null,
        null|array            $context = null,
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
                $expected = \is_string($context['expected'])
                    ? $context['expected']
                    : \get_debug_type($context['expected']);
                $message .= " expected to be of type '{$expected}'";
            }
            if ($context['received'] !== null) {
                $message .= ", received '" . \get_debug_type($context['received']) . "'";
            }
        }

        parent::__construct(
            message : $message,
            context : $context,
            previous: $previous,
        );
    }
}

<?php

declare(strict_types=1);

namespace Northrook;

class UndefinedEntryException extends RuntimeException
{
    /**
     * @param null|array<array-key, mixed> $context
     */
    public function __construct(
        int|string|object $key,
        null|string       $message = null,
        null|array        $context = null,
        null|\Throwable   $previous = null,
        int               $code = 0,
    ) {
        $context ??= [];

        if (\is_object($key)) {
            $context['key'] = \get_class($key) . '@' . \spl_object_id($key);
        } elseif (\is_int($key)) {
            $context['key'] = $key;
        } else {
            $string = \trim((string) $key);

            $context['key'] = \is_numeric($string)
                ? \intval($string)
                : $string;
        }

        $message ??= 'Undefined entry ' . match (gettype($key)) {
            'integer' => "at index `{$context['key']}`",
            'object'  => "for object `{$context['key']}`",
            'string' => empty($context['key'])
                ? 'for `empty` key'
                : "for key `{$context['key']}`",
        };

        parent::__construct($message, $context, $previous, $code);
    }

    final public function getKey(): int|string
    {
        $key = $this->getContext()['key'];

        if (\is_int($key) || \is_string($key)) {
            return $key;
        }

        throw new \InvalidArgumentException(
            static::class . '->context[key] is not an integer or string',
        );
    }
}

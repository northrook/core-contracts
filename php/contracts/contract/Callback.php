<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Exportable;

/**
 * Serializable deferred callable with optional bound arguments.
 *
 * Trust: `__serialize` / `__unserialize` assume already-authenticated bytes (HMAC / known
 * hash before `unserialize`, or in-process round-trip). Descriptor checks on hydrate are
 * theatre once the blob is trusted; untrusted `unserialize` is a caller bug, not a Callback hole.
 */
final class Callback implements Exportable
{
    /** Runtime cache only; never serialized. */
    private null|\Closure $fn = null;

    private function __construct(
        /** @var string|object|array{0: string, 1: string} */
        private string|object|array $descriptor,
        /** @var array<array-key, mixed> */
        private array $arguments = [],
    ) {}

    /**
     * @param callable-string  $name
     * @param mixed   ...$arguments
     *
     * @return \Northrook\Callback
     */
    public static function function(
        string   $name,
        mixed ...$arguments,
    ): static {
        return new static($name, $arguments);
    }

    /**
     * @param class-string  $class
     * @param string        $method
     * @param mixed         ...$args
     *
     * @return \Northrook\Callback
     */
    public static function staticMethod(
        string   $class,
        string   $method,
        mixed ...$args,
    ): static {
        return new static([$class, $method], $args);
    }

    /**
     * @param class-string $class
     * @param mixed        ...$arguments
     */
    public static function instantiate(
        string   $class,
        mixed ...$arguments,
    ): static {
        return new static(['new', $class], $arguments);
    }

    /**
     * Wrap a serializable invokable object.
     *
     * Rejects {@see \Closure} — anonymous functions are not natively serializable.
     *
     * @param object&callable  $object
     * @param mixed            ...$arguments
     */
    public static function invokable(
        object   $object,
        mixed ...$arguments,
    ): static {
        if ($object instanceof \Closure) {
            throw new InvalidArgumentException(
                message: 'Callback::invokable() does not accept Closure; pass a serializable invokable object',
                context: [
                    'object'    => $object,
                    'arguments' => $arguments,
                ],
            );
        }

        if (! \is_callable($object)) {
            throw new InvalidArgumentException(
                message: 'Object is not invokable',
                context: [
                    'object'    => $object,
                    'arguments' => $arguments,
                ],
            );
        }

        return new static($object, $arguments);
    }

    public function __invoke(
        mixed ...$arguments,
    ): mixed {
        return $this->closure()(...$this->arguments, ...$arguments);
    }

    private function closure(): \Closure
    {
        return $this->fn ??= match (true) {
            \is_string($this->descriptor) => $this->functionClosure($this->descriptor),
            \is_object($this->descriptor) => $this->invokableClosure($this->descriptor),
            \is_array($this->descriptor) => $this->classClosure($this->descriptor),
            default => throw new InvalidArgumentException(
                message: 'Callback descriptor is not callable',
                context: ['received' => $this->descriptor],
            ),
        };
    }

    private function functionClosure(
        string $name,
    ): \Closure {
        if (! \function_exists($name)) {
            throw new InvalidArgumentException(
                message: "Unknown function: {$name}",
                context: ['received' => $name],
            );
        }

        return $name(...);
    }

    private function invokableClosure(
        object $object,
    ): \Closure {
        if (! \is_callable($object)) {
            throw new InvalidArgumentException(
                message: 'Object is not invokable',
                context: ['received' => $object],
            );
        }

        return $object(...);
    }

    /**
     * @param array{0: string, 1: string} $descriptor
     */
    private function classClosure(
        array $descriptor,
    ): \Closure {
        if ($descriptor[0] === 'new') {
            $class = $descriptor[1];
            if (! \class_exists($class)) {
                throw new InvalidArgumentException(
                    message: "Unknown class: {$class}",
                    context: ['received' => $class],
                );
            }

            return $this->instantiateClosure($class);
        }

        return $this->staticMethodClosure($descriptor[0], $descriptor[1]);
    }

    /**
     * @param class-string $class
     */
    private function instantiateClosure(
        string $class,
    ): \Closure {
        return static fn(mixed ...$args): object => new $class(...$args);
    }

    private function staticMethodClosure(
        string $class,
        string $method,
    ): \Closure {
        if (! \is_callable([$class, $method])) {
            throw new InvalidArgumentException(
                message: "{$class}::{$method} is not callable",
                context: ['received' => ['class' => $class, 'method' => $method]],
            );
        }

        return $class::$method(...);
    }

    /**
     * @param string|object|array{0: string, 1: string}  $descriptor
     * @param array<array-key, mixed>                    $arguments
     */
    public static function restore(
        string|object|array $descriptor,
        array               $arguments = [],
    ): static {
        $callback = Reflect::class(Callback::class)->getInstance();
        $callback->__unserialize([
            'descriptor' => $descriptor,
            'arguments'  => $arguments,
        ]);

        return $callback;
    }

    public function _export(): string
    {
        $data = $this->__serialize();

        return Export::call(
            self::class,
            'restore',
            $data['descriptor'],
            $data['arguments'],
        );
    }

    /**
     * @return array{
     *     descriptor: string|object|array{0: string, 1: string},
     *     arguments: array<array-key,mixed>
     * }
     */
    public function __serialize(): array
    {
        return [
            'descriptor' => $this->descriptor,
            'arguments'  => $this->arguments,
        ];
    }

    /**
     * @param array{
     *      descriptor: string|object|array{0: string, 1: string},
     *      arguments: array<array-key,mixed>
     *  } $data
     */
    public function __unserialize(
        array $data,
    ): void {
        $this->descriptor = $data['descriptor'];
        $this->arguments  = $data['arguments'] ?? [];
        $this->fn         = null;
    }

    /**
     * @return array{
     *     descriptor: string|object|array{0: string, 1: string},
     *     arguments: array<array-key,mixed>
     * }
     */
    public function __debugInfo(): array
    {
        return $this->__serialize();
    }

    /**
     * @return array{
     *     descriptor: string|object|array{0: string, 1: string},
     *     arguments: array<array-key,mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->__serialize();
    }
}

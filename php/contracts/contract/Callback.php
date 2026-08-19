<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Serializable deferred callable with optional bound arguments.
 *
 * Trust: `__serialize` / `__unserialize` assume already-authenticated bytes (HMAC / known
 * hash before `unserialize`, or in-process round-trip). Descriptor checks on hydrate are
 * theatre once the blob is trusted; untrusted `unserialize` is a caller bug, not a Callback hole.
 */
final class Callback
{
    /** Runtime cache only; never serialized. */
    private null|\Closure $fn = null;

    private function __construct(
        /** @var string|object|array{0: string, 1: string} */
        private mixed $descriptor,
        /** @var list<mixed> */
        private array $boundArgs = [],
    ) {}

    /**
     * @param mixed ...$args
     */
    public static function function(
        string   $name,
        mixed ...$args,
    ): static {
        return new static($name, array_values($args));
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
        return new static([$class, $method], array_values($args));
    }

    /**
     * @param class-string $class
     * @param mixed        ...$args
     */
    public static function instantiate(
        string   $class,
        mixed ...$args,
    ): static {
        return new static(['new', $class], array_values($args));
    }

    /**
     * Wrap a serializable invokable object.
     *
     * Rejects {@see \Closure} — anonymous functions are not natively serializable.
     *
     * @param object&callable $obj
     * @param mixed           ...$args
     */
    public static function invokable(
        object   $obj,
        mixed ...$args,
    ): static {
        if ($obj instanceof \Closure) {
            throw new InvalidArgumentException(
                message: 'Callback::invokable() does not accept Closure; pass a serializable invokable object',
                context: [
                    'name'     => 'obj',
                    'expected' => 'serializable invokable object',
                    'received' => $obj,
                ],
            );
        }

        if (! \is_callable($obj)) {
            throw new InvalidArgumentException(
                message: 'Object is not invokable',
                context: [
                    'name'     => 'obj',
                    'expected' => 'invokable object',
                    'received' => $obj,
                ],
            );
        }

        return new static($obj, array_values($args));
    }

    public function __invoke(
        mixed ...$args,
    ): mixed {
        return $this->closure()(...$this->boundArgs, ...$args);
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
     * @return array{descriptor: string|object|array{0: string, 1: string}, args: list<mixed>}
     */
    public function __serialize(): array
    {
        return [
            'descriptor' => $this->descriptor,
            'args'       => $this->boundArgs,
        ];
    }

    /**
     * @param array{descriptor: string|object|array{0: string, 1: string}, args?: list<mixed>} $data
     */
    public function __unserialize(
        array $data,
    ): void {
        $this->descriptor = $data['descriptor'];
        $this->boundArgs  = $data['args'] ?? [];
        $this->fn         = null;
    }
}

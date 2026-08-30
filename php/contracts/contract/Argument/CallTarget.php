<?php

declare(strict_types=1);

namespace Northrook\Argument;

use Northrook\InvalidArgumentException;
use Northrook\RuntimeException;

/**
 * Named function, or class plus optional method.
 *
 * - `T` is the class on the class branch, nnused when `$function` is set.
 * - Does not support {@see \Closure}.
 *
 * @template-covariant T of object
 */
final readonly class CallTarget
{
    /** @var null|callable-string */
    public null|string $function;

    /** @var null|class-string */
    public null|string $class;

    /** @var null|non-empty-string */
    public null|string $method;

    /**
     * @param callable-string|T|class-string<T> $target
     * @param null|non-empty-string             $method
     */
    public function __construct(
        object|string $target,
        null|string   $method = null,
        bool          $validate = false,
    ) {
        if ($target instanceof \Closure) {
            throw new InvalidArgumentException(
                message: 'CallTarget does not accept Closure.',
                context: ['target' => $target],
            );
        }

        $method = $method === ''
            ? null
            : $method;

        if (\is_object($target)) {
            $this->function = null;
            $this->class    = $target::class;
            $this->method   = $method;
        }
        elseif ($method !== null) {
            $this->function = null;
            $this->class    = $this->classString($target);
            $this->method   = $method;
        }
        elseif (\str_contains($target, '::')) {
            [$class, $parsed] = \explode('::', $target, 2);
            $this->function = null;
            $this->class    = $this->classString($class);
            $this->method   = $this->methodName($parsed);
        }
        elseif (\is_callable($target)) {
            $this->function = $target;
            $this->class    = null;
            $this->method   = null;
        }
        else {
            $this->class    = self::classString($target);
            $this->method   = null;
            $this->function = null;
        }

        if ($validate) {
            $this->validate();
        }
    }

    /**
     * @param mixed ...$arguments
     *
     * @return mixed
     */
    public function __invoke(
        mixed ...$arguments,
    ): mixed {
        if ($this->function) {
            return ( $this->function )(...$arguments);
        }

        if (\is_callable([$this->class, $this->method])) {
            return [$this->class, $this->method](...$arguments);
        }

        if ($this->class) {
            return new $this->class(...$arguments);
        }

        throw new RuntimeException(
            message: 'CallTarget is not a valid callable',
            context: ['callTarget' => $this],
        );
    }

    /**
     * @template TTarget of object
     *
     * @param callable-string|TTarget|class-string<TTarget>|array{0: TTarget|class-string<TTarget>, 1?: null|non-empty-string} $value
     *
     * @return CallTarget<TTarget>
     */
    public static function from(
        object|string|array $value,
        bool                $validate = false,
    ): CallTarget {
        if (\is_array($value)) {
            $target = $value[0] ?? null;
            $method = $value[1] ?? null;

            if (! \is_object($target) && ! \is_string($target)) {
                throw new InvalidArgumentException(
                    message: '$value[0] must be an object or class-string.',
                    context: ['value' => $value],
                );
            }

            if ($method !== null && ( ! \is_string($method) || $method === '' )) {
                throw new InvalidArgumentException(
                    message: '$value[1] must be a non-empty method name.',
                    context: ['value' => $value],
                );
            }

            return new CallTarget($target, $method, $validate);
        }

        return new CallTarget($value, null, $validate);
    }

    /**
     * @return class-string
     */
    private function classString(
        string $value,
    ): string {
        if (\is_class_string($value)) {
            return $value;
        }

        throw new InvalidArgumentException(
            message: 'CallTarget class must be a class-string.',
            context: ['class' => $value],
        );
    }

    /**
     * @return non-empty-string
     */
    private function methodName(
        string $method,
    ): string {
        if (\trim($method) === '') {
            throw new InvalidArgumentException(
                message: 'CallTarget string with :: must be class::method.',
                context: ['method' => $method],
            );
        }

        return $method;
    }

    private function validate(): void
    {
        if ($this->function !== null) {
            if (! \is_callable($this->function)) {
                throw new InvalidArgumentException(
                    message: "Function '{$this->function}' is not callable.",
                    context: ['function' => $this->function],
                );
            }

            return;
        }

        if ($this->class === null || ! \class_exists($this->class)) {
            throw new InvalidArgumentException(
                message: "Class '{$this->class}' does not exist.",
                context: [
                    'class'  => $this->class,
                    'method' => $this->method,
                ],
            );
        }

        if ($this->method && ! \method_exists($this->class, $this->method)) {
            throw new InvalidArgumentException(
                message: "Method '{$this->method}' does not exist on class '{$this->class}'.",
                context: [
                    'class'  => $this->class,
                    'method' => $this->method,
                ],
            );
        }
    }
}

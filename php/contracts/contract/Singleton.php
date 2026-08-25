<?php

declare(strict_types=1);

namespace Northrook;

/**
 * Lazy singleton with optional eager `register()` on subclasses.
 *
 * @example Zero-argument
 * ```
 * final class Clock extends Singleton {}
 * Clock::get();
 * ```
 *
 * @example Eager registration
 * ```
 * final class App extends Singleton
 * {
 *     private function __construct(
 *         private readonly LoggerInterface $logger,
 *         bool $selfInstantiated = false,
 *     ) {
 *         parent::__construct($selfInstantiated);
 *     }
 *
 *     public static function register(LoggerInterface $logger): static
 *     {
 *         return new self($logger);
 *     }
 *
 *     protected static function create(): static
 *     {
 *         throw new LogicException(self::class.' must be register()ed before get()');
 *     }
 * }
 * ```
 */
abstract class Singleton
{
    /** @var array<class-string, object|false> */
    private static array $_instance = [];

    /**
     * @var false|string `true` when born via {@see get()}/{@see create()};
     *                   `false` when constructed explicitly (e.g. `register()`)
     */
    protected private(set) false|string $_selfInstantiated = false;

    /**
     *
     * @protected
     */
    protected function __construct()
    {
        self::assertUnregistered();
        self::$_instance[static::class] = $this;
    }

    final public static function isRegistered(): bool
    {
        return isset(self::$_instance[static::class]) && self::$_instance[static::class] !== false;
    }

    final protected static function assertUnregistered(): void
    {
        /** @var static|null $instance */
        $instance = self::$_instance[static::class] ?? null;

        if ($instance === false) {
            throw new RuntimeException(
                message: static::class . ' was permanently unregistered and cannot be instantiated again.',
            );
        }

        if ($instance !== null) {
            throw new RuntimeException(
                message: static::class . ' is already registered and cannot be instantiated twice.',
                context: [
                    'class'            => $instance::class,
                    'instance'         => $instance,
                    'selfInstantiated' => $instance->_selfInstantiated,
                ],
            );
        }
    }

    /**
     * Returns the memoized instance, creating it on first access via {@see create()}.
     */
    final public static function get(): static
    {
        $slot = self::$_instance[static::class] ?? null;

        if ($slot === false) {
            throw new RuntimeException(
                message: static::class . ' was permanently unregistered and cannot be retrieved.',
                context: [
                    'class'     => static::class,
                    'timestamp' => Timestamp::now(),
                ],
            );
        }

        if ($slot instanceof static) {
            return $slot;
        }

        try {
            $instance = self::$_instance[static::class] = static::create();

            $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0];
            $timestamp = Timestamp::now();

            $file = $backtrace['file'] ?? null;
            $line = $backtrace['line'] ?? null;

            if ($file !== null && $line !== null) {
                $location = "{$file}:{$line}@{$timestamp}";
            }
            else {
                throw new LogicException(
                    message: 'debug_backtrace() failed to provide file/line.',
                );
            }

            $instance->_selfInstantiated = $location;

            return $instance;
        }
        catch (\Throwable $exception) {
            throw new RuntimeException(
                message : static::class . ' failed to initialize via get().',
                context : [
                    'class'     => static::class,
                    'timestamp' => Timestamp::now(),
                ],
                previous: $exception,
            );
        }
    }

    /**
     * First-access factory for {@see get()} when no instance is registered yet.
     *
     * Default assumes a zero-arg (or defaults-only) constructor and marks the
     * instance as self-instantiated. Override to call `register()`, or to throw
     * when registration is required.
     *
     * Subclasses with a different constructor signature must override this method.
     */
    protected static function create(): static
    {
        return new static;
    }

    /**
     * Drops this instance from the registry.
     *
     * - `$resettable = true` — vacates the slot; a later `get()`/`register()` may create again
     * - `$resettable = false` — burns the slot (`false`); further construct/`get()` fail closed
     */
    final protected function unregisterSingletonInstance(
        bool $resettable = false,
    ): void {
        $instance = self::$_instance[static::class];

        if ($instance instanceof Singleton && method_exists($instance, '__destruct')) {
            $instance->__destruct();
        }

        if ($resettable) {
            unset(self::$_instance[static::class]);
        }
        else {
            self::$_instance[static::class] = false;
        }
    }

    final public function __serialize(): array
    {
        throw new LogicException(
            message: $this::class . ' is a singleton and cannot be serialized.',
        );
    }

    final public function __clone(): never
    {
        throw new LogicException(
            message: $this::class . ' is a singleton and cannot be cloned.',
        );
    }
}

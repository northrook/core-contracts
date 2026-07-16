<?php

declare(strict_types=1);

namespace Northrook\Contracts;

use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\Interfaces\SingletonInterface;

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
abstract class Singleton implements SingletonInterface
{
    /** @var array<class-string, object|false> */
    private static array $__instance = [];

    protected readonly Timestamp $__timestamp;

    /**
     * @param bool  $__selfInstantiated  `true` when born via {@see get()}/{@see create()};
     *                               `false` when constructed explicitly (e.g. `register()`)
     *
     * @protected
     */
    protected function __construct(
        protected readonly bool $__selfInstantiated = false,
    ) {
        $instance = self::$__instance[static::class] ?? null;

        if ($instance === false) {
            throw new RuntimeException(
                message: $this::class . ' was permanently unregistered and cannot be instantiated again.',
                context: [
                    'class'            => $this::class,
                    'timestamp'        => Timestamp::now(),
                    'selfInstantiated' => $this->__selfInstantiated,
                ],
            );
        }

        if ($instance !== null) {
            throw new RuntimeException(
                message: $this::class . ' is already registered and cannot be instantiated twice.',
                context: [
                    'instance'         => $instance,
                    'class'            => $this::class,
                    'timestamp'        => Timestamp::now(),
                    'selfInstantiated' => $this->__selfInstantiated,
                ],
            );
        }

        $this->__timestamp = Timestamp::now();

        self::$__instance[static::class] = $this;
    }

    final public static function isRegistered(): bool
    {
        return isset(self::$__instance[static::class]) && self::$__instance[static::class] !== false;
    }

    /**
     * Returns the memoized instance, creating it on first access via {@see create()}.
     */
    final public static function get(): static
    {
        $slot = self::$__instance[static::class] ?? null;

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
            return self::$__instance[static::class] = static::create();
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                message: static::class . ' failed to initialize via get().',
                context: [
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
        return new static(__selfInstantiated: true);
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
        if ($resettable) {
            unset(self::$__instance[static::class]);
        } else {
            self::$__instance[static::class] = false;
        }
    }

    final public function __clone(): void
    {
        throw new RuntimeException(
            message: $this::class . ' is a singleton and cannot be cloned.',
        );
    }
}

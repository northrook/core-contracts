<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * @method static static register()
 */
abstract class ContractSingleton
{
    private static null|self $instance = null;

    protected readonly float $registeredTimestamp;

    /**
     * @protected
     */
    protected function __construct()
    {
        if (static::$instance !== null) {
            throw new \LogicException(
                $this::class . ' is a singleton and cannot be instantiated twice.',
            );
        }

        $this->registeredTimestamp = \microtime(true);

        static::$instance = $this;
    }

    /**
     * Returns the singleton instance.
     *
     * {@see static::register()} must be called before this method.
     */
    final public static function get(): static
    {
        return self::getInstance();
    }

    final public static function isRegistered(): bool
    {
        return isset(self::$instance);
    }

    /**
     * @internal auto-ininitializer, calls {@see static::register()} if not already registered
     */
    final protected static function getInstance(): static
    {
        return self::$instance ??= self::register();
    }
}

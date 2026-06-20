<?php

declare(strict_types = 1);

namespace Northrook\Contracts;

abstract class ContractSingleton
{
    protected static null|self $instance = null;

    protected readonly float $registeredTimestamp;

    final public static function isRegistered(): bool
    {
        return isset(self::$instance);
    }

    protected function __construct()
    {
        if (static::$instance !== null) {
            throw new \LogicException($this::class . ' is a singleton and cannot be instantiated twice.');
        }

        $this->registeredTimestamp = \microtime(true);

        static::$instance = $this;
    }

    /**
     *
     * @abstract
     *
     * @return self
     */
    public static function register(): self
    {
        throw new \BadMethodCallException('This method must be implemented by the child class.');
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts;

/**
 * @method static self register()
 *
 * @requires-method static register():self
 */
abstract class ContractSingleton
{
    protected static null|self $instance = null;

    protected readonly float $registeredTimestamp;

    final protected static function getInstance(): self
    {
        return self::$instance ??= self::register();
    }

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
}

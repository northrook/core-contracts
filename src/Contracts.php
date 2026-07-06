<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\ContractSingleton;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * May in time hold some configuration
 */
final class Contracts extends ContractSingleton
{
    public const string VERSION = '0.1.0';

    private function __construct(
        private readonly LoggerInterface $logger,
        private readonly \DateTimeZone $timezone = new \DateTimeZone('UTC'),
    ) {
        parent::__construct();
    }

    public static function timezone(): \DateTimeZone
    {
        return self::get()->timezone;
    }

    public static function log(): LoggerInterface
    {
        return self::get()->logger;
    }

    public static function register(
        null|LoggerInterface $logger = null,
    ): static {
        return new self($logger ?? new NullLogger());
    }
}

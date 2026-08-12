<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Assert;
use Northrook\Contracts\CurlInterface;
use Northrook\Contracts\Directory;
use Northrook\Contracts\FilesystemInterface;
use Northrook\Contracts\Singleton;
use Northrook\Contracts\System;
use Northrook\Contracts\Timezone;
use Northrook\Contracts\Value\Redactor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class Contracts extends Singleton
{
    private function __construct(
        public readonly Directory $rootDirectory,
        public readonly Directory $varDirectory,
        public readonly LoggerInterface $logger,
        public readonly Timezone $timezone,
        public readonly null|CurlInterface $curl,
        public readonly null|FilesystemInterface $filesystem,
        public readonly null|Redactor $secretRedactor,
        //
        bool $__selfInstantiated = false,
    ) {
        parent::__construct($__selfInstantiated);
    }

    public static function tryGet(): null|Contracts
    {
        return self::isRegistered() ? self::get() : null;
    }

    /**
     * @param null|string|\Stringable                                   $rootDirectory  Via {@see System::resolveRootDirectory()}
     * @param null|string|\Stringable                                   $varDirectory   App-private `var/` tree via {@see System::resolveVarDirectory()}; created if missing
     * @param null|LoggerInterface                                      $logger         `null` → {@see NullLogger}
     * @param null|string|\Stringable|\DateTimeZone|\DateTimeInterface  $timezone       Via {@see Timezone::from()}; `null` → UTC
     * @param null|CurlInterface                                        $curl           Optional shared client; stored as-is
     * @param null|FilesystemInterface                                  $filesystem     Optional I/O collaborator; stored as-is
     * @param null|Redactor                                             $secretRedactor Optional dump redactor
     */
    public static function register(
        null|string|\Stringable                                      $rootDirectory = null,
        null|string|\Stringable                                      $varDirectory = null,
        null|LoggerInterface                                           $logger = null,
        null|string|\Stringable|\DateTimeZone|\DateTimeInterface $timezone = null,
        null|CurlInterface                                             $curl = null,
        null|FilesystemInterface                                       $filesystem = null,
        null|Redactor                                                  $secretRedactor = null,
    ): static {
        $rootDirectory = System::resolveRootDirectory($rootDirectory);
        $varDirectory  = System::resolveVarDirectory($rootDirectory, $varDirectory);

        Assert::validDirectory($rootDirectory, __METHOD__);
        Assert::validDirectory($varDirectory, __METHOD__, create: true);

        return new self(
            rootDirectory : new Directory($rootDirectory),
            varDirectory  : new Directory($varDirectory),
            logger        : $logger ?? new NullLogger,
            timezone      : Timezone::from($timezone),
            curl          : $curl,
            filesystem    : $filesystem,
            secretRedactor: $secretRedactor,
        );
    }

    protected static function create(): static
    {
        return static::register();
    }
}

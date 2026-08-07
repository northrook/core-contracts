<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Contracts\Assert;
use Northrook\Contracts\CurlInterface;
use Northrook\Contracts\Directory;
use Northrook\Contracts\FilesystemInterface;
use Northrook\Contracts\Redactor;
use Northrook\Contracts\Singleton;
use Northrook\Contracts\System;
use Northrook\Contracts\Timezone;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class Contracts extends Singleton
{
    private function __construct(
        public readonly Directory $rootDirectory,
        public readonly Directory $cacheDirectory,
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
     * @param null|Redactor  $secretRedactor  Global dump redactor; `null` → {@see Redactor}
     */
    public static function register(
        null|string|\Stringable                                  $rootDirectory = null,
        null|string|\Stringable                                  $cacheDirectory = null,
        null|LoggerInterface                                     $logger = null,
        null|string|\Stringable|\DateTimeZone|\DateTimeInterface $timezone = null,
        null|CurlInterface                                       $curl = null,
        null|FilesystemInterface                                 $filesystem = null,
        null|Redactor                                            $secretRedactor = null,
    ): static {
        $rootDirectory  = System::resolveRootDirectory($rootDirectory);
        $cacheDirectory = System::resolveCacheDirectory($rootDirectory, $cacheDirectory);

        Assert::validDirectory($rootDirectory, __METHOD__);
        Assert::validDirectory($cacheDirectory, __METHOD__, create: true);

        return new self(
            rootDirectory : new Directory($rootDirectory),
            cacheDirectory: new Directory($cacheDirectory),
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

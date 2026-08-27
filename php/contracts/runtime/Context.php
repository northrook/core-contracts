<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Context\AppDebug;
use Northrook\Context\AppEnv;
use Northrook\Context\ContextManager;
use Northrook\Context\OsFamily;
use Northrook\Contracts\ContextEnum;
use Northrook\Contracts\PlatformContext;
use Northrook\Contracts\RuntimeContext;
use Northrook\Kernel\KernelContext;
use Northrook\Logger\Log;
use Northrook\Logger\NativeLogger;
use Psr\Log\LoggerInterface;

/**
 * @static
 */
final class Context
{
    public const bool CLI = \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';

    private static null|Context $instance = null;

    public private(set) Instantiated $instantiated;

    public private(set) AppEnv $appEnv;
    public private(set) AppDebug $appDebug;
    public private(set) OsFamily $osFamily;
    public private(set) Timezone $timezone;

    public private(set) string $rootDirectory;
    public private(set) string $varDirectory;

    public private(set) LoggerInterface $logger;

    private function __construct(
        public readonly ContextManager $context,
    ) {
        if (Context::$instance !== null) {
            throw new RuntimeException(
                message: static::class . ' is already registered and cannot be instantiated twice.',
                context: isset($this->instantiated)
                    ? ['instantiated' => $this->instantiated]
                    : [],
            );
        }

        Context::$instance = $this;
    }

    public function setLogger(
        LoggerInterface $logger,
    ): self {
        $this->logger = $logger;
        return $this;
    }

    public function setTimezone(
        int|string|\Stringable|\DateTimeZone|\DateTimeInterface $timezone,
    ): self {
        $this->timezone = Timezone::from($timezone);
        return $this;
    }

    public static function register(
        null|AppEnv                                                  $appEnv = null,
        null|AppDebug                                                $appDebug = null,
        null|OsFamily                                                $osFamily = null,
        null|int|string|\Stringable|\DateTimeZone|\DateTimeInterface $timezone = null,

        null|string|\Stringable                                      $rootDirectory = null,
        null|string|\Stringable                                      $varDirectory = null,

        null|ContextManager                                          $contextManager = null,
        null|LoggerInterface                                         $logger = null,
    ): Context {
        $context = new Context(
            $contextManager ?? new ContextManager,
        );

        $context->appEnv   = AppEnv::resolve($appEnv);
        $context->appDebug = AppDebug::resolve($appDebug, $context->appEnv);
        $context->osFamily = OsFamily::resolve($osFamily);

        $context->rootDirectory = \resolve_root_directory($rootDirectory);
        $context->varDirectory  = \resolve_var_directory($context->rootDirectory, $varDirectory);

        $context->logger = $logger ?? new NativeLogger(
                \str_starts_with($context->varDirectory, $context->rootDirectory)
                    ? $context->varDirectory . DIR_SEP . 'logs'
                    : null,
            );


        $context->setTimezone($timezone ?? 'UTC');

        if ($logger) {
            $context->logger = $logger;
            $context->context->setLogger($logger, false);
        }

        return $context;
    }

    //region Context

    /**
     * Whether every given context value matches the registered {@see Context}.
     *
     * @param class-string<\Northrook\Contracts\ContextEnum>|\Northrook\Contracts\ContextEnum|Filter  ...$context
     */
    public static function is(
        string|ContextEnum|Filter ...$context,
    ): bool {
        if (empty($context)) {
            return false;
        }

        $filter = Filter::resolve(
            from : $context,
            unset: true,
        );

        /**
         * @var class-string<\Northrook\Contracts\ContextEnum>[]|\Northrook\Contracts\ContextEnum[] $context
         */
        $instance = null;
        $evaluate = static function(
            string|ContextEnum $value,
        ) use (&$instance): bool {
            $match = match (true) {
                $value instanceof PlatformContext => $value->resolve(),
                $value instanceof AppEnv => Context::appEnv() === $value,
                $value instanceof AppDebug => Context::appDebug()->isEnabled($value),
                $value instanceof OsFamily => Context::osFamily() === $value,
                $value instanceof RuntimeContext, \is_class_string($value) => null,
                default => self::handleUnknownType($value),
            };

            if ($match === null) {
                if (! Context::isRegistered()) {
                    return false;
                }
                return ( $instance ??= self::get() )->context->has($value);
            }

            return $match;
        };

        return match ($filter) {
            Filter::OR  => \array_any($context, $evaluate),
            Filter::NOT => ! \array_any($context, $evaluate),
            default     => \array_all($context, $evaluate),
        };
    }

    public static function isTrusted(): bool
    {
        return ! Context::isUntrusted();
    }

    public static function isUntrusted(): bool
    {
        // Failsafe conditions are never trusted.
        if (Context::isFailsafe()) {
            return true;
        }

        // HTTP Requests and Responses are always untrusted.
        if (Context::isRegistered() && ( Context::is(KernelContext::Request) || Context::is(KernelContext::Response) )) {
            return true;
        }

        return false;
    }

    //endregion Context

    //region Getters

    public static function rootDirectory(): string
    {
        return Context::get()->rootDirectory ??= \resolve_root_directory();
    }

    public static function varDirectory(): string
    {
        return Context::get()->varDirectory ??= \resolve_var_directory(
            Context::rootDirectory(),
        );
    }

    public static function logger(): LoggerInterface
    {
        return Context::get()->logger;
    }

    public static function timezone(): Timezone
    {
        return Context::get()->timezone ??= Timezone::from();
    }

    public static function appEnv(): AppEnv
    {
        return Context::get()->appEnv ??= AppEnv::resolve();
    }

    public static function appDebug(): AppDebug
    {
        return Context::get()->appDebug ??= AppDebug::resolve(appEnv: Context::appEnv());
    }

    public static function osFamily(): OsFamily
    {
        return Context::get()->osFamily ??= OsFamily::resolve();
    }

    public static function isProduction(): bool
    {
        return Context::appEnv() === AppEnv::Production;
    }

    public static function isDevelopment(): bool
    {
        return Context::appEnv() === AppEnv::Development;
    }

    public static function isTesting(): bool
    {
        return Context::appEnv() === AppEnv::Testing;
    }

    public static function isFailsafe(): bool
    {
        return Context::appEnv() === AppEnv::Failsafe;
    }

    public static function isStaging(): bool
    {
        return Context::appEnv() === AppEnv::Staging;
    }

    public static function isDebug(): bool
    {
        return Context::appDebug() !== AppDebug::Disabled;
    }

    //endregion Getters

    public static function isRegistered(): bool
    {
        return Context::$instance !== null;
    }

    private static function get(): Context
    {
        if (Context::$instance !== null) {
            return Context::$instance;
        }

        static::$instance = Context::register();

        $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0];
        $timestamp = Timestamp::now();
        $file      = $backtrace['file'] ?? null;
        $line      = $backtrace['line'] ?? null;

        if ($file !== null && $line !== null) {
            static::$instance->instantiated = new Instantiated(
                $file,
                $line,
                $timestamp->number,
            );
        }
        else {
            throw new LogicException(
                message: 'debug_backtrace() failed to provide file/line.',
            );
        }

        return static::$instance;
    }

    private static function handleUnknownType(
        string|ContextEnum $value,
    ): false {
        Log::warning(
            message: 'Unknown context type: ' . \debug_value_type($value, true),
            context: ['value' => $value],
        );
        return false;
    }
}

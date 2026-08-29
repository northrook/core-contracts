<?php

declare(strict_types=1);

namespace Northrook;

use Northrook\Context\AppDebug;
use Northrook\Context\AppEnv;
use Northrook\Context\ContextEntry;
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
 * Process runtime: platform state and typed {@see ContextEnum} map.
 *
 * Registered once via {@see register()}; static accessors lazy-register when unset.
 * Enum reads and writes use the registered instance ({@see has()}, {@see update()}, …).
 *
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

    /**
     * Displaced Context entries, most-recent first.
     *
     * @var list<ContextEntry>
     */
    public array $history {
        get => $this->manager->history;
    }

    /**
     * Currently active Context entries.
     *
     * @var list<ContextEntry>
     */
    public array $current {
        get => $this->manager->current;
    }

    /**
     * Soft-lock against mutation ({@see freeze()}).
     */
    public bool $frozen {
        get => $this->manager->frozen;
    }

    private function __construct(
        private readonly ContextManager $manager,
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

    /**
     * Active {@see ContextEntry} for the given enum class, or `null` when unset.
     *
     * Keys by enum class; the case of a passed {@see ContextEnum} is ignored.
     */
    public function entry(
        string|ContextEnum $context,
    ): null|ContextEntry {
        return $this->manager->entry($context);
    }

    /**
     * Whether a Context is active.
     *
     * - Class-string: true when that enum class has any active case.
     * - Case: true only when that exact case is active.
     *
     * @param ContextEnum  $context
     *
     * @return bool
     *
     * @phpstan-impure
     */
    public function has(
        string|ContextEnum $context,
    ): bool {
        return $this->manager->has($context);
    }

    /**
     * Whether every given Context is active.
     *
     * Returns `false` when no arguments are passed.
     *
     * @phpstan-impure
     */
    public function hasAll(
        string|ContextEnum ...$context,
    ): bool {
        return $this->manager->hasAll(...$context);
    }

    /**
     * Whether any given Context is active.
     *
     * Returns `false` when no arguments are passed.
     *
     * @phpstan-impure
     */
    public function hasAny(
        string|ContextEnum ...$context,
    ): bool {
        return $this->manager->hasAny(...$context);
    }

    /**
     * Sets a {@see ContextEnum} case; returns the previous case if set.
     *
     * No-op when the same case is already active.
     *
     * @template T of \Northrook\Contracts\ContextEnum
     *
     * @param T  $context
     *
     * @return null|T
     */
    public function replace(
        ContextEnum $context,
    ): null|ContextEnum {
        return $this->manager->replace($context);
    }

    /**
     * Active case for the enum class of `$default`, or `$default` after {@see update()}.
     *
     * @template T of \Northrook\Contracts\ContextEnum
     *
     * @param T  $default
     *
     * @return T
     */
    public function resolve(
        ContextEnum $default,
    ): ContextEnum {
        return $this->manager->resolve($default);
    }

    /**
     * Updates one or more {@see ContextEnum} cases.
     *
     * Identical cases are skipped. Each enum class may appear at most once.
     */
    public function update(
        ContextEnum ...$context,
    ): void {
        $this->manager->update(...$context);
    }

    /**
     * Replaces all Context entries.
     *
     * - Omitted classes are displaced into history.
     * - Calling this with no arguments is equivalent to {@see clear()}.
     *
     * @param \Northrook\Contracts\ContextEnum  ...$context
     */
    public function set(
        ContextEnum ...$context,
    ): void {
        $this->manager->set(...$context);
    }

    /**
     * Removes active Contexts.
     *
     * - Class-string: clears that enum class regardless of the active case.
     * - Case: clears only when that exact case is active; otherwise a no-op.
     *
     * @param ContextEnum  ...$context
     */
    public function unset(
        ContextEnum|string ...$context,
    ): void {
        $this->manager->unset(...$context);
    }

    /**
     * Displaces every active Context entry into history, then empties the map.
     */
    public function clear(): void
    {
        $this->manager->clear();
    }

    /**
     * Empties the Context map and history.
     */
    public function reset(): void
    {
        $this->manager->reset();
    }

    /**
     * Soft-lock against further mutation.
     *
     * - Unfreezing in an untrusted context throws.
     */
    public function freeze(
        bool $set = true,
    ): void {
        $this->manager->freeze($set);
    }

    /**
     * Assign the runtime logger.
     */
    public function setLogger(
        LoggerInterface $logger,
    ): self {
        $this->logger = $logger;
        $this->manager->setLogger($logger, false);
        return $this;
    }

    /** Set the runtime timezone. */
    public function setTimezone(
        int|string|\Stringable|\DateTimeZone|\DateTimeInterface $timezone,
    ): self {
        $this->timezone = Timezone::from($timezone);
        return $this;
    }

    /**
     * Register the process {@see Context} singleton.
     *
     * Unset arguments resolve from env / defaults. Throws when already registered.
     */
    public static function register(
        null|AppEnv $appEnv = null,
        null|AppDebug $appDebug = null,
        null|OsFamily $osFamily = null,
        null|int|string|\Stringable|\DateTimeZone|\DateTimeInterface $timezone = null,

        null|string|\Stringable $rootDirectory = null,
        null|string|\Stringable $varDirectory = null,

        /**
         * @internal Test injection only.
         */
        null|ContextManager $contextManager = null,
        null|LoggerInterface $logger = null,
    ): Context {
        if (Context::$instance !== null) {
            throw new RuntimeException(
                message: static::class . ' is already registered and cannot be instantiated twice.',
                context: isset(Context::$instance->instantiated)
                    ? ['instantiated' => Context::$instance->instantiated]
                    : [],
            );
        }

        $context = new Context(
            manager: $contextManager ?? new ContextManager,
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
            $context->setLogger($logger);
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
                return ( $instance ??= self::get() )->has($value);
            }

            return $match;
        };

        return match ($filter) {
            Filter::OR  => \array_any($context, $evaluate),
            Filter::NOT => ! \array_any($context, $evaluate),
            default     => \array_all($context, $evaluate),
        };
    }

    /** Inverse of {@see isUntrusted()}. */
    public static function isTrusted(): bool
    {
        return ! Context::isUntrusted();
    }

    /**
     * Whether the runtime is in an exposure-sensitive mode.
     *
     * True for Failsafe and HTTP Request/Response {@see KernelContext} cases.
     */
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

    /** Whether {@see register()} has run (or lazy registration occurred). */
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

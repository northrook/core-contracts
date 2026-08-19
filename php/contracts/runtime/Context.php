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
use Northrook\Filesystem\Directory;
use Northrook\Filesystem\NativeFilesystem;
use Northrook\Kernel\KernelContext;
use Northrook\Logger\NativeLogger;
use Override;
use Psr\Log\LoggerInterface;

/**
 * # Localization related Context:
 *
 * We need to be able to update them during runtime; say the runtime is passed a UK locale,
 * but the site can serve multiple languages-- the incoming request needs to be able to set that.
 *
 * Using an `enum` for this would be difficult; direct string input or check if PHP has a built-in solution.
 *
 */

/**
 * @static
 */
final class Context extends Singleton
{
    public const bool CLI = \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';

    private static null|AppEnv   $_appEnv   = null;
    private static null|AppDebug $_appDebug = null;
    private static null|OsFamily $_osFamily = null;

    private readonly ContextManager $context;

    public readonly AppEnv $appEnv;
    public readonly AppDebug $appDebug;
    public readonly OsFamily $osFamily;

    /** @var ContextEnum[]  */
    public array $currentContext {
        get => \array_map(
            static fn(ContextEntry $context) => $context->context,
            $this->context->current,
        );
    }

    public private(set) Redactor $secretRedactor {
        get => $this->secretRedactor ??= new Redactor;
    }

    public ColorScheme $colorScheme {
        get => $this->context->resolve(ColorScheme::Light);
    }

    private function __construct(
        public readonly Directory           $rootDirectory,
        public readonly Directory           $varDirectory,
        public readonly Timezone            $timezone,

        public readonly LoggerInterface     $logger,
        public readonly FilesystemInterface $filesystem,
        public readonly null|CurlInterface  $curlClient,

        ContextManager                      $contextManager,

        null|Redactor                       $secretRedactor,
    ) {
        $this->appEnv   = Context::appEnv();
        $this->appDebug = Context::appDebug();
        $this->osFamily = Context::osFamily();
        $this->context  = $contextManager;
        if ($secretRedactor)
            $this->secretRedactor = $secretRedactor;

        parent::__construct();
    }

    public function __destruct()
    {
        $this->context->__destruct();
    }

    public static function register(
        null|AppEnv                                              $appEnv = null,
        null|AppDebug                                            $appDebug = null,
        null|OsFamily                                            $osFamily = null,
        null|ContextManager                                      $contextManager = null,
        null|LoggerInterface                                     $logger = null,

        null|string|\Stringable                                  $rootDirectory = null,
        null|string|\Stringable                                  $varDirectory = null,

        null|string|\Stringable|\DateTimeZone|\DateTimeInterface $timezone = null,

        null|CurlInterface                                       $curlClient = null,
        null|FilesystemInterface                                 $filesystem = null,
        null|Redactor                                            $secretRedactor = null,
    ): static {
        self::assertUnregistered();

        Context::$_appEnv   = AppEnv::resolve($appEnv);
        Context::$_appDebug = AppDebug::resolve($appDebug, Context::$_appEnv);
        Context::$_osFamily = OsFamily::resolve($osFamily);

        $timezone = Timezone::from($timezone);

        $logger         ??= new NativeLogger;
        $filesystem     ??= new NativeFilesystem;
        $contextManager ??= new ContextManager($logger);

        $rootDirectory = new Directory(
            path      : \resolve_root_directory($rootDirectory),
            assert    : true,
            filesystem: $filesystem,
        );

        $varDirectory = new Directory(
            path      : \resolve_var_directory($rootDirectory, $varDirectory),
            create    : true,
            filesystem: $filesystem,
        );

        return new self(
            rootDirectory : $rootDirectory,
            varDirectory  : $varDirectory,
            timezone      : $timezone,
            logger        : $logger,
            filesystem    : $filesystem,
            curlClient    : $curlClient,
            contextManager: $contextManager,
            secretRedactor: $secretRedactor,
        );
    }

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

    public static function appEnv(): AppEnv
    {
        return Context::$_appEnv ??= AppEnv::resolve();
    }

    public static function appDebug(): AppDebug
    {
        return Context::$_appDebug ??= AppDebug::resolve(appEnv: Context::appEnv());
    }

    public static function osFamily(): OsFamily
    {
        return Context::$_osFamily ??= OsFamily::resolve();
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

    /**
     * Returns the registered instance, or `null` without auto-registering.
     */
    public static function tryGet(): null|static
    {
        return self::isRegistered() ? self::get() : null;
    }

    #[Override]
    protected static function create(): static
    {
        return static::register();
    }

    private static function handleUnknownType(
        string|ContextEnum $value,
    ): false {
        ( Context::tryGet()->logger ?? new NativeLogger )->warning(
            message: 'Unknown context type: ' . \debug_value_type($value, true),
            context: ['value' => $value],
        );
        return false;
    }
}

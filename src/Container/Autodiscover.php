<?php

declare(strict_types=1);

namespace Core\Contracts\Container;

use Attribute;
use InvalidArgumentException;
use ReflectionClass;
use Throwable;
use ValueError;
use Stringable;

/**
 * @template T of object
 */
#[Attribute( Attribute::TARGET_CLASS )]
class Autodiscover implements Stringable
{
    public readonly ?string $id;

    /** @var class-string<T> */
    public readonly string $class;

    /** @var array<array-key, array<array-key, string>|string> */
    public readonly array $roles;

    /** @var null|bool|list<bool|class-string|string> */
    public readonly null|bool|array $alias;

    /** @var array<string, array<array-key,mixed>> */
    public readonly array $callMethods;

    /**
     * ## `$role`
     * Tag this service with one or more roles, with optional arguments.
     * ```
     * role : [
     *   'tagged.role' => [ .. arguments ]
     * ]
     * ```
     *
     * ## `$callMethod`
     * Public methods to be called on instantiation.
     * ```
     * callMethod : [
     *   'methodName' => [ .. arguments ],
     * ]
     * ```
     *
     * ## `$scope`
     * How to handle instantiation.
     * - `container` provides a singleton, shared instance
     * - `service` provides a new instance per service
     * - `clone` instantiates a new independent object every time
     * - `null` uses the `container` default
     *
     * ## `$autowire`
     * Enable autowiring service `__construct` dependencies.
     *
     * `Contracts\Autowire` dependencies will always be provided.
     *
     * ## `$lazy`
     * Defer service instantiation.
     *
     * ## `$alias`
     * - `string|string[]` Set one or more aliases for the service.
     * - `true` The `container` will auto-alias relevant interfaces
     * - `false` disables auto-aliasing
     *  - `null` uses the `container` default
     *
     * ## `$arguments`
     * Provide `__construct` arguments for this class.
     *
     * ## `$fromStatic`
     * Provide a `service::staticMethod` to use when instantiating the service.
     *
     * Uses `autowire` and `$arguments` if set
     *
     * @param array<array-key, array<string, string>|string>|string  $role
     * @param null|false|string|string[]                             $alias
     * @param array<string, array<array-key, mixed>>|string|string[] $callMethod
     * @param null|'clone'|'container'|'service'                     $scope
     * @param null|bool                                              $autowire
     * @param null|bool                                              $lazy
     * @param array<array-key, mixed>                                $arguments
     * @param null|string                                            $fromStatic
     */
    public function __construct(
        string|array            $role = [],
        null|false|string|array $alias = null,
        string|array            $callMethod = [],
        public readonly ?string $scope = null,
        public readonly ?bool   $autowire = null,
        public readonly ?bool   $lazy = null,
        public readonly array   $arguments = [],
        public readonly ?string $fromStatic = null,
    ) {
        $this->alias = ( $alias === null || $alias === false ) ? $alias : \array_values( (array) $alias );
        $this->roles = \is_string( $role ) === true ? [$role => []] : $role;

        $callMethods = [];

        foreach ( \is_string( $callMethod ) ? [$callMethod => []] : $callMethod as $key => $value ) {
            if ( \is_int( $key ) ) {
                if ( \is_string( $value ) ) {
                    $key   = $value;
                    $value = [];
                }
                else {
                    throw new InvalidArgumentException(
                        "Unable to set 'callMethod[{$key}]', invalid format.\n"
                            ."Only 'methodName', 'methodName[]', or 'methodName=>arguments[]' accepted.\n"
                            .\var_export( $callMethod, true ),
                    );
                }
            }

            $callMethods[$key] = (array) $value;
        }

        $this->callMethods = $callMethods;
    }

    /**
     * @internal called by the {@see ContainerInterface}
     *
     * @param class-string<T> $class
     * @param ?string         $id
     *
     * @return self<T>
     */
    final public function register( string $class, ?string $id = null ) : self
    {
        $this->class = \class_exists( $class )
                ? $class
                : throw new InvalidArgumentException(
                    $this::class." cannot autodiscover class '{$class}', it does not exist.",
                );

        $this->id = $id;

        $this->configure();

        return $this;
    }

    /**
     * @return ReflectionClass<T>
     */
    final public function getReflectionClass() : ReflectionClass
    {
        \assert(
            \class_exists( $this->class ),
            $this::class." cannot reflect class '{$this->class}', it does not exist.",
        );

        return new ReflectionClass( $this->class );
    }

    /**
     * @return string
     */
    final public function getClassFilePath() : string
    {
        static $classFilePath;

        if ( isset( $classFilePath ) ) {
            return $classFilePath;
        }

        try {
            $reflect              = $this->getReflectionClass();
            $filePath             = $reflect->getFileName() ?: throw new ValueError();
            return $classFilePath = $filePath;
        }
        catch ( Throwable $exception ) {
            throw new InvalidArgumentException(
                message  : "Could not derive directory path from '{$this->class}'.\n {$exception->getMessage()}.",
                previous : $exception,
            );
        }
    }

    protected function configure() : void {}

    /**
     * @return class-string<T>
     */
    final public function __toString() : string
    {
        return $this->class;
    }
}

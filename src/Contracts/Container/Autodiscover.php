<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container;

use Attribute;
use InvalidArgumentException;
use LogicException;
use Northrook\Contracts\Container\Service\Scope;
use ReflectionClass;
use SplFileInfo;
use Stringable;

const VALID_SCOPES = [
    Scope::AUTO,
    Scope::CONTAINER,
    Scope::SERVICE,
    Scope::CLONE,
];

/**
 * Declares a class as a container-managed service.
 *
 * Applied to a class, this attribute supplies registration metadata — scope,
 * roles, aliases, constructor arguments, and post-instantiation calls — that
 * the container reads during compilation. {@see register()} binds the target
 * class once the attribute is discovered via reflection.
 *
 * @template T of object = object
 *
 * @phpstan-type AutodiscoverState array{
 *     id?: ?string,
 *     className?: class-string<T>,
 *     alias?: null|false|list<class-string|string>,
 *     roles?: array<array-key, array<array-key, string>|string>,
 *     callMethods?: array<string, array<array-key, mixed>>,
 *     scope?: null|'clone'|'container'|'service',
 *     autowire?: ?bool,
 *     lazy?: ?bool,
 *     arguments?: array<array-key, mixed>,
 *     fromStatic?: ?string,
 *     classFileInfo?: SplFileInfo,
 * }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Autodiscover implements Stringable
{
    private bool $registered = false;

    /** @var AutodiscoverState */
    private array $properties = [];

    public null|string $id {
        get => $this->properties['id'] ?? $this->notInitialized();
    }

    /** @var class-string<T> */
    public string $class {
        get => $this->properties['className'] ?? $this->notInitialized();
    }

    /** @var null|false|list<class-string|string> */
    public null|bool|array $alias {
        get => $this->properties['alias'] ?? $this->notInitialized();
    }

    /** @var array<array-key, array<array-key, string>|string> */
    public array $roles {
        get => $this->properties['roles'] ?? $this->notInitialized();
    }

    /** @var array<string, array<array-key,mixed>> */
    public array $callMethods {
        get => $this->properties['callMethods'] ?? $this->notInitialized();
    }

    /** @var null|'clone'|'container'|'service' */
    public null|string $scope {
        get => $this->properties['scope'] ?? $this->notInitialized();
    }

    public null|bool $autowire {
        get => $this->properties['autowire'] ?? $this->notInitialized();
    }

    public null|bool $lazy {
        get => $this->properties['lazy'] ?? $this->notInitialized();
    }

    /** @var array<array-key, mixed> */
    public array $arguments {
        get => $this->properties['arguments'] ?? $this->notInitialized();
    }

    /** @var null|string */
    public null|string $fromStatic {
        get => $this->properties['fromStatic'] ?? $this->notInitialized();
    }

    /** @var SplFileInfo */
    public SplFileInfo $classFileInfo {
        get => $this->properties['classFileInfo'] ?? $this->notInitialized();
    }

    /**
     * ## `$role`
     * Tag this service with one or more roles, with optional arguments.
     * ```
     * role : [
     *   'tagged.role' => [ ... arguments ]
     * ]
     * ```
     *
     * ## `$callMethod`
     * Public methods to be called on instantiation.
     * ```
     * callMethod : [
     *   'methodName' => [ ... arguments ],
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
     * `..\Container\Autowire` dependencies will always be provided.
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
        string|array $role = [],
        null|false|string|array $alias = null,
        string|array $callMethod = [],
        null|string $scope = null,
        null|bool $autowire = null,
        null|bool $lazy = null,
        array $arguments = [],
        null|string $fromStatic = null,
    ) {
        $this
            ->setRoles($role)
            ->setAlias($alias)
            ->setCallMethods($callMethod)
            ->setScope($scope)
            ->setAutowire($autowire)
            ->setLazy($lazy)
            ->setArguments($arguments)
            ->setFromStatic($fromStatic);
    }

    /**
     * @internal called by the {@see ContainerInterface}
     *
     * @param class-string<T> $class
     * @param ?string         $id
     *
     * @return self<T>
     */
    final public function register(string $class, null|string $id = null): self
    {
        \assert($this->registered === false, $this::class . ' cannot be registered twice.');

        $this->setClass($class)->setID($id)->configure();

        $this->validate();

        $this->registered = true;

        return $this;
    }

    /**
     * @return ReflectionClass<T>
     */
    final public function getReflectionClass(): ReflectionClass
    {
        \assert(
            \class_exists($this->class),
            $this::class . " cannot reflect class '{$this->class}', it does not exist.",
        );

        return new ReflectionClass($this->class);
    }

    /**
     * @return class-string<T>
     */
    final public function __toString(): string
    {
        return $this->class;
    }

    protected function configure(): void {}

    /**
     * @param array<array-key, array<string, string>|string>|string $roles
     *
     * @return self<T>
     */
    final protected function setRoles(string|array $roles): self
    {
        \assert($this->registered === false, __METHOD__ . ' must be before registering.');

        $this->properties['roles'] = \is_string($roles) === true ? [$roles => []] : $roles;

        foreach (\array_keys($this->properties['roles']) as $role) {
            \assert(is_ascii($role), $this::class . " cannot use role {$role}, only ASCII is allowed.");

            \assert(\strlen($role) > 1, $this::class . " cannot use role {$role}, it must be longer than 1 character.");

            \assert(
                \strlen($role) < 1_024,
                $this::class . " cannot use role {$role}, it must not be longer than 1024 characters.",
            );
        }

        return $this;
    }

    /**
     * @param null|array<class-string|string>|false|string $alias
     *
     * @return self<T>
     */
    final protected function setAlias(null|false|string|array $alias = null): self
    {
        \assert($this->registered === false, __METHOD__ . ' must be before registering.');

        if ($alias === null || $alias === false) {
            $this->properties['alias'] = $alias;
        } elseif (\is_string($alias)) {
            $this->properties['alias'] = [$alias];
        } else {
            $this->properties['alias'] = \array_values($alias);
        }

        if (\is_array($this->properties['alias'])) {
            foreach ($this->properties['alias'] as $alias) {
                \assert(is_ascii($alias), $this::class . " cannot use alias {$alias}, only ASCII is allowed.");

                \assert(
                    \strlen($alias) > 1,
                    $this::class . " cannot use alias {$alias}, it must be longer than 1 character.",
                );

                \assert(
                    \strlen($alias) < 1_024,
                    $this::class . " cannot use alias {$alias}, it must not be longer than 1024 characters.",
                );
            }
        }

        return $this;
    }

    /**
     * @param array<int|string, mixed>|string $callMethod
     *
     * @return self<T>
     */
    final protected function setCallMethods(string|array $callMethod = []): self
    {
        \assert($this->registered === false, __METHOD__ . ' must be before registering.');

        $callMethods = [];

        foreach (\is_string($callMethod) ? [$callMethod => []] : $callMethod as $key => $value) {
            if (\is_int($key)) {
                if (\is_string($value)) {
                    $key   = $value;
                    $value = [];
                } else {
                    throw new InvalidArgumentException(
                        "Unable to set 'callMethod[{$key}]', invalid format.\n"
                            . "Only 'methodName', 'methodName[]', or 'methodName=>arguments[]' accepted.\n"
                            . \var_export($callMethod, true),
                    );
                }
            }

            $callMethods[$key] = (array) $value;
        }

        $this->properties['callMethods'] = $callMethods;

        return $this;
    }

    /**
     * @return self<T>
     * @param  ?string $scope
     */
    final protected function setScope(null|string $scope): self
    {
        \assert($this->registered === false, __METHOD__ . ' must be before registering.');

        \assert(
            \in_array($scope, VALID_SCOPES, true),
            $this::class . " cannot set scope '{$scope}', it must be one of: " . \implode(', ', VALID_SCOPES),
        );

        $this->properties['scope'] = $scope;

        return $this;
    }

    /**
     * @return self<T>
     * @param  ?bool   $autowire
     */
    final protected function setAutowire(null|bool $autowire): self
    {
        \assert($this->registered === false, __METHOD__ . ' must be before registering.');

        $this->properties['autowire'] = $autowire;

        return $this;
    }

    /**
     * @return self<T>
     * @param  ?bool   $lazy
     */
    final protected function setLazy(null|bool $lazy): self
    {
        \assert($this->registered === false, __METHOD__ . ' must be before registering.');

        $this->properties['lazy'] = $lazy;

        return $this;
    }

    /**
     * @param array<array-key, mixed> $arguments
     *
     * @return self<T>
     */
    final protected function setArguments(array $arguments = []): self
    {
        \assert($this->registered === false, __METHOD__ . ' must be before registering.');

        $this->properties['arguments'] = $arguments;

        return $this;
    }

    /**
     * @return self<T>
     * @param  ?string $fromStatic
     */
    final protected function setFromStatic(null|string $fromStatic): self
    {
        \assert($this->registered === false, __METHOD__ . ' must be before registering.');

        $this->properties['fromStatic'] = $fromStatic;

        return $this;
    }

    private function validate(): void
    {
        $fromStatic = $this->properties['fromStatic'] ?? null;

        \assert(
            $fromStatic === null || \is_callable($fromStatic),
            $this::class . ' cannot use static initializer ' . $fromStatic . ', it is not callable.',
        );

        $reflection = $this->getReflectionClass();

        foreach (\array_keys($this->properties['callMethods'] ?? []) as $callMethod) {
            \assert(
                $reflection->hasMethod($callMethod),
                $this::class . " does not have required method '{$callMethod}'",
            );
        }
    }

    /**
     * @param class-string<T> $class
     *
     * @return self<T>
     */
    private function setClass(string $class): self
    {
        \assert(\class_exists($class), $this::class . " cannot register {$class}, it does not exist.");

        $this->properties['className'] = $class;

        $filePath = $this->getReflectionClass()->getFileName();

        \assert($filePath !== false, $this::class . " cannot register {$class}, its source file is unknown.");

        \assert(\file_exists($filePath), $this::class . " cannot register {$class}, it does not exist at {$filePath}.");

        $this->properties['classFileInfo'] = new SplFileInfo($filePath);

        return $this;
    }

    /**
     * @return self<T>
     * @param  ?string $id
     */
    private function setID(null|string $id = null): self
    {
        if ($id !== null) {
            \assert(is_ascii($id), $this::class . " cannot use ID {$id}, only ASCII is allowed.");

            \assert(\strlen($id) > 1, $this::class . " cannot use ID {$id}, it must be longer than 1 character.");

            \assert(
                \strlen($id) < 1_024,
                $this::class . " cannot use ID {$id}, it must not be longer than 1024 characters.",
            );
        }

        $this->properties['id'] = $id;

        return $this;
    }

    private function notInitialized(): never
    {
        throw new LogicException('Call ' . $this::class . '->register first.');
    }
}

function is_ascii(string $string): bool
{
    $i = 0;
    while (isset($string[$i])) {
        if (\ord($string[$i]) & 0x80) {
            return false;
        }

        $i++;
    }

    return true;
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Container;

use Attribute;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use SplFileInfo;
use Stringable;

use function Northrook\Contracts\is_valid_key;

const VALID_SCOPES = [
    null,
    'container',
    'service',
    'clone',
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
     * string : 'role'
     * array  : [
     *   'role',
     *   'tagged.role' => [ ... arguments ],
     * ]
     * ```
     *
     * ## `$callMethod`
     * Public methods to be called on instantiation.
     * ```
     *  string : 'methodName'
     *  array  : [
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
     * @param array<callable-string, array<array-key, mixed>>|callable-string|callable-string[] $callMethod
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
        if ($this->registered) {
            throw new LogicException($this::class . ' cannot be registered twice.');
        }

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
        if (! \class_exists($this->class)) {
            throw new InvalidArgumentException(
                $this::class . " cannot reflect class '{$this->class}', it does not exist.",
            );
        }

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
    final protected function setRoles(
        string|array $roles,
    ): self {
        $this->assertNotRegistered(__METHOD__);

        if (\is_string($roles)) {
            $this->properties['roles'] = [$roles => []];
        } else {
            $normalized = [];

            foreach ($roles as $key => $value) {
                if (\is_int($key)) {
                    if (\is_string($value)) {
                        $key   = $value;
                        $value = [];
                    } else {
                        throw new InvalidArgumentException(
                            "Unable to set 'role[{$key}]', invalid format.\n"
                                . "Only 'roleName', or 'roleName=>arguments[]' accepted.\n"
                                . \var_export($roles, true),
                        );
                    }
                }

                $normalized[$key] = (array) $value;
            }

            $this->properties['roles'] = $normalized;
        }

        foreach (\array_keys($this->properties['roles']) as $role) {
            $this->validateKey(
                $role,
                __METHOD__,
            );
        }

        return $this;
    }

    /**
     * @param null|array<class-string|string>|false|string $alias
     *
     * @return self<T>
     */
    final protected function setAlias(
        null|false|string|array $alias = null,
    ): self {
        $this->assertNotRegistered(__METHOD__);

        if ($alias === null || $alias === false) {
            $this->properties['alias'] = $alias;
        } elseif (\is_string($alias)) {
            $this->properties['alias'] = [$alias];
        } else {
            $this->properties['alias'] = \array_values($alias);
        }

        if (\is_array($this->properties['alias'])) {
            foreach ($this->properties['alias'] as $alias) {
                $this->validateKey(
                    $alias,
                    __METHOD__,
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
    final protected function setCallMethods(
        string|array $callMethod = [],
    ): self {
        $this->assertNotRegistered(__METHOD__);

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
    final protected function setScope(
        null|string $scope,
    ): self {
        $this->assertNotRegistered(__METHOD__);

        if (! \in_array($scope, VALID_SCOPES, true)) {
            throw new InvalidArgumentException(
                $this::class . " cannot set scope '{$scope}', it must be one of: " . \implode(', ', VALID_SCOPES),
            );
        }

        $this->properties['scope'] = $scope;

        return $this;
    }

    /**
     * @return self<T>
     * @param  ?bool   $autowire
     */
    final protected function setAutowire(
        null|bool $autowire,
    ): self {
        $this->assertNotRegistered(__METHOD__);

        $this->properties['autowire'] = $autowire;

        return $this;
    }

    /**
     * @return self<T>
     * @param  ?bool   $lazy
     */
    final protected function setLazy(
        null|bool $lazy,
    ): self {
        $this->assertNotRegistered(__METHOD__);

        $this->properties['lazy'] = $lazy;

        return $this;
    }

    /**
     * @param array<array-key, mixed> $arguments
     *
     * @return self<T>
     */
    final protected function setArguments(
        array $arguments = [],
    ): self {
        $this->assertNotRegistered(__METHOD__);

        $this->properties['arguments'] = $arguments;

        return $this;
    }

    /**
     * @return self<T>
     * @param  ?string $fromStatic
     */
    final protected function setFromStatic(
        null|string $fromStatic,
    ): self {
        $this->assertNotRegistered(__METHOD__);

        $this->properties['fromStatic'] = $fromStatic;

        return $this;
    }

    private function validate(): void
    {
        $fromStatic = $this->properties['fromStatic'] ?? null;

        if ($fromStatic !== null && ! \is_callable($fromStatic)) {
            throw new InvalidArgumentException(
                $this::class . ' cannot use static initializer ' . $fromStatic . ', it is not callable.',
            );
        }

        $reflection = $this->getReflectionClass();

        foreach (\array_keys($this->properties['callMethods'] ?? []) as $callMethod) {
            if (! $reflection->hasMethod($callMethod)) {
                throw new InvalidArgumentException(
                    $this::class . " does not have required method '{$callMethod}'",
                );
            }
        }
    }

    /**
     * @param class-string<T> $class
     *
     * @return self<T>
     */
    private function setClass(
        string $class,
    ): self {
        if (! \class_exists($class)) {
            throw new InvalidArgumentException($this::class . " cannot register {$class}, it does not exist.");
        }

        $this->properties['className'] = $class;

        $filePath = $this->getReflectionClass()->getFileName();

        if ($filePath === false) {
            throw new InvalidArgumentException(
                $this::class . " cannot register {$class}, its source file is unknown.",
            );
        }

        if (! \file_exists($filePath)) {
            throw new InvalidArgumentException(
                $this::class . " cannot register {$class}, it does not exist at {$filePath}.",
            );
        }

        $this->properties['classFileInfo'] = new SplFileInfo($filePath);

        return $this;
    }

    /**
     * @return self<T>
     * @param  ?string $id
     */
    private function setID(
        null|string $id = null,
    ): self {
        if ($id !== null) {
            $this->validateKey(
                $id,
                __METHOD__,
            );
        }

        $this->properties['id'] = $id;

        return $this;
    }

    private function notInitialized(): never
    {
        throw new LogicException('Call ' . $this::class . '->register first.');
    }

    private function assertNotRegistered(
        string $caller,
    ): void {
        if ($this->registered) {
            throw new LogicException($caller . ' must be called before registering.');
        }
    }

    private function validateKey(
        string $string,
        string $caller,
    ): void {
        if (! is_valid_key(
            key: $string,
            min: 1,
            max: 1_024,
            separator: '.',
            charset: \CHARSET_ALNUM . '-_\\/',
        )) {
            throw new InvalidArgumentException(
                "{$caller} cannot use key '{$string}'"
                . ', must be dot-separated alphanumeric segments (dash, underscore, slash, backslash allowed)'
                . ' between 1 and 1024 characters.',
            );
        }
    }
}

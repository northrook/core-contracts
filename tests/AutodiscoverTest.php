<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use InvalidArgumentException;
use LogicException;
use Northrook\Contracts\Container\Autodiscover;
use Northrook\Contracts\Container\Service\Role;
use Northrook\Contracts\Container\Service\Scope;
use Northrook\Contracts\Tests\Support\AutodiscoverFixture;
use Northrook\Contracts\Tests\Support\InvalidAutodiscoverCalls;
use PHPUnit\Framework\TestCase;

final class AutodiscoverTest extends TestCase
{
    /** @var class-string<AutodiscoverFixture> */
    private string $targetClass;

    protected function setUp(): void
    {
        $this->targetClass = AutodiscoverFixture::class;
    }

    public function testContainerAttributeClassesAutoload(): void
    {
        self::assertTrue(\class_exists(Autodiscover::class));
        self::assertTrue(\class_exists(Scope::class));
        self::assertTrue(\class_exists(Role::class));
    }

    public function testRegisterWithStringRole(): void
    {
        $definition = (new Autodiscover(role: 'middleware'))->register($this->targetClass);

        self::assertSame(['middleware' => []], $definition->roles);
        self::assertSame($this->targetClass, $definition->class);
    }

    public function testRegisterWithListRoles(): void
    {
        $definition = (new Autodiscover(role: ['middleware', 'listener']))->register($this->targetClass);

        self::assertSame(
            ['middleware' => [], 'listener' => []],
            $definition->roles,
        );
    }

    public function testRegisterWithMixedRoleList(): void
    {
        $definition = (new Autodiscover(role: [
            'middleware',
            'tagged.role' => ['priority' => '10'],
        ]))->register($this->targetClass);

        self::assertSame(
            ['middleware' => [], 'tagged.role' => ['priority' => '10']],
            $definition->roles,
        );
    }

    public function testRoleAttributeRegisters(): void
    {
        $definition = (new Role('middleware'))->register($this->targetClass);

        self::assertSame(['middleware' => []], $definition->roles);
    }

    public function testScopeAttributeRegisters(): void
    {
        $definition = (new Scope(Scope::CONTAINER))->register($this->targetClass);

        self::assertSame('container', $definition->scope);
    }

    public function testInvalidScopeThrowsOnConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        InvalidAutodiscoverCalls::invalidScope();
    }

    public function testDoubleRegisterThrows(): void
    {
        $definition = (new Autodiscover())->register($this->targetClass);

        $this->expectException(LogicException::class);

        $definition->register($this->targetClass);
    }
}

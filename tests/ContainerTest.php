<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\CompilerInterface;
use Northrook\Contracts\Container\AutodiscoverInterface;
use Northrook\Contracts\Container\BindingAttribute;
use Northrook\Contracts\Container\CompilerPass;
use Northrook\Contracts\Container\CompilerPassInterface;
use Northrook\Contracts\Container\ServiceBinding;
use Northrook\Contracts\ContainerInterface;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\Service\Inline;
use Northrook\Contracts\Service\Scoped;
use Northrook\Contracts\Service\Shared;
use Northrook\Contracts\Service\Unique;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    // ── ServiceBinding enum ──────────────────────────────────────

    #[DataProvider('provideServiceBindingCases')]
    public function testServiceBindingValues(
        ServiceBinding $case,
        string         $value,
    ): void {
        self::assertSame($value, $case->value);
        self::assertSame($case, ServiceBinding::from($value));
    }

    public static function provideServiceBindingCases(): \Generator
    {
        yield 'inline' => [ServiceBinding::Inline, 'inline'];
        yield 'scoped' => [ServiceBinding::Scoped, 'scoped'];
        yield 'shared' => [ServiceBinding::Shared, 'shared'];
        yield 'unique' => [ServiceBinding::Unique, 'unique'];
    }

    #[DataProvider('provideServiceBindingResolveCases')]
    public function testServiceBindingResolve(
        string|BindingAttribute $value,
        ServiceBinding          $expected,
    ): void {
        self::assertSame($expected, ServiceBinding::resolve($value));
    }

    public static function provideServiceBindingResolveCases(): \Generator
    {
        yield 'inline string' => ['inline', ServiceBinding::Inline];
        yield 'scoped string' => ['scoped', ServiceBinding::Scoped];
        yield 'shared string' => ['shared', ServiceBinding::Shared];
        yield 'unique string' => ['unique', ServiceBinding::Unique];
        yield 'inline attribute' => [new Inline, ServiceBinding::Inline];
        yield 'scoped attribute' => [new Scoped, ServiceBinding::Scoped];
        yield 'shared attribute' => [new Shared, ServiceBinding::Shared];
        yield 'unique attribute' => [new Unique, ServiceBinding::Unique];
    }

    public function testServiceBindingResolveRejectsUnknownString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ServiceBinding::resolve('bogus');
    }

    // ── CompilerPass enum ────────────────────────────────────────

    #[DataProvider('provideCompilerPassCases')]
    public function testCompilerPassValues(
        CompilerPass $case,
        string       $value,
    ): void {
        self::assertSame($value, $case->value);
        self::assertSame($case, CompilerPass::from($value));
    }

    public static function provideCompilerPassCases(): \Generator
    {
        yield 'initialization' => [CompilerPass::INITIALIZATION, 'compiler.initialization'];
        yield 'discovery' => [CompilerPass::DISCOVERY, 'compiler.discovery'];
        yield 'parse' => [CompilerPass::PARSE, 'compiler.parse'];
        yield 'optimize' => [CompilerPass::OPTIMIZE, 'compiler.optimize'];
        yield 'validate' => [CompilerPass::VALIDATE, 'compiler.validate'];
        yield 'compile' => [CompilerPass::COMPILE, 'compiler.compile'];
    }

    public function testCompilerInterfacePassesMatchEnumDeclarationOrder(): void
    {
        self::assertSame(
            CompilerPass::cases(),
            CompilerInterface::PASSES,
        );
    }

    public function testCompilerInterfacePassesAreSequentiallyIndexed(): void
    {
        self::assertSame([0, 1, 2, 3, 4, 5], \array_keys(CompilerInterface::PASSES));
    }

    // ── Interfaces ───────────────────────────────────────────────

    /**
     * @param class-string $contract
     */
    #[DataProvider('provideInterfaces')]
    public function testContractIsInterface(
        string $contract,
    ): void {
        self::assertTrue(new \ReflectionClass($contract)->isInterface());
    }

    /**
     * @return \Generator<string, array{class-string}>
     */
    public static function provideInterfaces(): \Generator
    {
        yield 'AutodiscoverInterface' => [AutodiscoverInterface::class];
        yield 'BindingAttribute' => [BindingAttribute::class];
        yield 'CompilerPassInterface' => [CompilerPassInterface::class];
        yield 'CompilerInterface' => [CompilerInterface::class];
        yield 'ContainerInterface' => [ContainerInterface::class];
    }

    public function testBindingAttributeExtendsAutodiscoverInterface(): void
    {
        self::assertContains(
            AutodiscoverInterface::class,
            new \ReflectionClass(BindingAttribute::class)->getInterfaceNames(),
        );
    }

    public function testContainerInterfaceExtendsPsrContainer(): void
    {
        self::assertContains(
            \Psr\Container\ContainerInterface::class,
            new \ReflectionClass(ContainerInterface::class)->getInterfaceNames(),
        );
    }

    public function testCompilerPassInterfaceDeclaresProcess(): void
    {
        $method = new \ReflectionMethod(CompilerPassInterface::class, 'process');

        self::assertSame('void', (string) $method->getReturnType());
        self::assertCount(1, $method->getParameters());
        self::assertSame(
            CompilerInterface::class,
            (string) $method->getParameters()[0]->getType(),
        );
    }
}

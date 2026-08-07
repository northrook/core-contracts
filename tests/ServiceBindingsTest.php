<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Callback;
use Northrook\Contracts\Container\AutodiscoverInterface;
use Northrook\Contracts\Container\BindingAttribute;
use Northrook\Contracts\Container\ServiceBinding;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Service\Alias;
use Northrook\Contracts\Service\Autodiscover;
use Northrook\Contracts\Service\Inline;
use Northrook\Contracts\Service\Scoped;
use Northrook\Contracts\Service\Shared;
use Northrook\Contracts\Service\Tag;
use Northrook\Contracts\Service\Unique;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceBindingsTest extends TestCase
{
    // ── Alias ────────────────────────────────────────────────────

    public function testAliasDeduplicatesAndSorts(): void
    {
        // @phpstan-ignore-next-line Fictional alias strings.
        $alias = new Alias('Service\\B', 'Service\\A', 'Service\\B');

        self::assertSame(['Service\\A', 'Service\\B'], $alias->aliases);
    }

    public function testAliasFromStringAndArray(): void
    {
        // @phpstan-ignore-next-line Fictional alias strings.
        self::assertSame(['Service\\A'], Alias::from('Service\\A')->aliases);
        self::assertSame(
            ['Service\\A', 'Service\\B'],
            // @phpstan-ignore-next-line Fictional alias strings.
            Alias::from(['Service\\B', 'Service\\A'])->aliases,
        );
    }

    public function testAliasMerge(): void
    {
        $merged = Alias::merge(
            // @phpstan-ignore-next-line Fictional alias strings.
            Alias::from('Service\\B'),
            // @phpstan-ignore-next-line Fictional alias strings.
            Alias::from(['Service\\C', 'Service\\A']),
        );

        self::assertSame(['Service\\A', 'Service\\B', 'Service\\C'], $merged->aliases);
    }

    public function testAliasIsIterable(): void
    {
        // @phpstan-ignore-next-line Fictional alias strings.
        $alias = Alias::from(['Service\\B', 'Service\\A']);

        self::assertSame($alias->aliases, \iterator_to_array($alias));
    }

    public function testAliasEmptyConstructor(): void
    {
        self::assertSame([], new Alias()->aliases);
    }

    public function testAliasImplementsAutodiscoverInterface(): void
    {
        self::assertInstanceOf(AutodiscoverInterface::class, new Alias);
        self::assertInstanceOf(\IteratorAggregate::class, new Alias);
    }

    // ── Tag ──────────────────────────────────────────────────────

    public function testTagWithoutArguments(): void
    {
        $tag = new Tag('role.logger');

        self::assertSame('role.logger', $tag->reference);
        self::assertNull($tag->arguments);
    }

    public function testTagWithArguments(): void
    {
        $tag = new Tag('role.logger', 'channel', 2);

        self::assertSame(['channel', 2], $tag->arguments);
    }

    public function testTagFromString(): void
    {
        $tag = Tag::from('role.logger');

        self::assertSame('role.logger', $tag->reference);
        self::assertNull($tag->arguments);
    }

    public function testTagFromArrayShiftsReference(): void
    {
        $tag = Tag::from(['role.logger', 'channel']);

        self::assertSame('role.logger', $tag->reference);
        self::assertSame(['channel'], $tag->arguments);
    }

    public function testTagFromMergesArrayValueWithExtraArguments(): void
    {
        $tag = Tag::from(['role.logger', 'inline'], 'extra');

        self::assertSame('role.logger', $tag->reference);
        self::assertSame(['extra', 'inline'], $tag->arguments);
    }

    public function testTagRejectsEmptyReference(): void
    {
        $this->expectException(RuntimeException::class);
        // @phpstan-ignore-next-line Testing invalid input.
        new Tag('');
    }

    public function testTagRejectsReferenceStartingWithDigit(): void
    {
        $this->expectException(RuntimeException::class);
        new Tag('0tag');
    }

    // ── Autodiscover ─────────────────────────────────────────────

    public function testAutodiscoverDefaultsAreNull(): void
    {
        $autodiscover = new Autodiscover;

        self::assertNull($autodiscover->binding);
        self::assertNull($autodiscover->aliases);
        self::assertNull($autodiscover->tags);
        self::assertNull($autodiscover->arguments);
        self::assertNull($autodiscover->autowire);
        self::assertNull($autodiscover->preload);
        self::assertNull($autodiscover->factory);
        self::assertNull($autodiscover->callbacks);
    }

    public function testAutodiscoverResolvesStringBinding(): void
    {
        self::assertSame(
            ServiceBinding::Unique,
            new Autodiscover(binding: 'unique')->binding,
        );
    }

    public function testAutodiscoverPassesEnumBindingThrough(): void
    {
        self::assertSame(
            ServiceBinding::Scoped,
            new Autodiscover(binding: ServiceBinding::Scoped)->binding,
        );
    }

    public function testAutodiscoverRejectsInvalidStringBinding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid service binding: `bogus`.');
        // @phpstan-ignore-next-line Testing invalid input.
        new Autodiscover(binding: 'bogus');
    }

    public function testAutodiscoverResolvesAliases(): void
    {
        self::assertSame(
            ['Service\\A'],
            // @phpstan-ignore-next-line Fictional alias strings.
            new Autodiscover(alias: 'Service\\A')->aliases?->aliases,
        );
        self::assertSame(
            ['Service\\A', 'Service\\B'],
            // @phpstan-ignore-next-line Fictional alias strings.
            new Autodiscover(alias: ['Service\\B', 'Service\\A'])->aliases?->aliases,
        );
    }

    public function testAutodiscoverEmptyAliasIsNull(): void
    {
        // @phpstan-ignore-next-line Empty alias is normalized to null.
        self::assertNull(new Autodiscover(alias: '')->aliases);
        self::assertNull(new Autodiscover(alias: [])->aliases);
    }

    public function testAutodiscoverResolvesSingleTag(): void
    {
        $tags = new Autodiscover(tag: 'role.logger')->tags;

        if ($tags === null) {
            self::fail('Expected Autodiscover tags to be resolved.');
        }

        self::assertSame(
            ['role.logger'],
            \array_map(static fn(Tag $tag): string => $tag->reference, $tags),
        );
        self::assertSame(
            [null],
            \array_map(static fn(Tag $tag): null|array => $tag->arguments, $tags),
        );
    }

    public function testAutodiscoverResolvesTagList(): void
    {
        // @phpstan-ignore-next-line Valid tag shapes.
        $tags = new Autodiscover(tags: ['role.a', ['role.b', 'arg']])->tags;

        if ($tags === null) {
            self::fail('Expected Autodiscover tags to be resolved.');
        }

        self::assertSame(
            ['role.a', 'role.b'],
            \array_map(static fn(Tag $tag): string => $tag->reference, $tags),
        );
        self::assertSame(
            [null, ['arg']],
            \array_map(static fn(Tag $tag): null|array => $tag->arguments, $tags),
        );
    }

    public function testAutodiscoverCombinesTagAndTags(): void
    {
        // @phpstan-ignore-next-line Valid tag shapes.
        $tags = new Autodiscover(
            tag: 'role.a',
            tags: ['role.b'],
        )->tags;

        if ($tags === null) {
            self::fail('Expected Autodiscover tags to be resolved.');
        }

        self::assertSame(['role.a', 'role.b'], \array_map(
            static fn(Tag $tag): string => $tag->reference,
            $tags,
        ));
    }

    public function testAutodiscoverResolvesCallbacks(): void
    {
        $callback = Callback::function(
            'strtolower',
        );

        $single = new Autodiscover(callbacks: $callback)->callbacks;
        self::assertSame([$callback], $single);

        $list = new Autodiscover(callbacks: [$callback])->callbacks;
        self::assertSame([$callback], $list);

        self::assertNull(new Autodiscover(callbacks: [])->callbacks);
    }

    public function testAutodiscoverResolvesArguments(): void
    {
        self::assertSame(
            ['mode' => 'x', 0 => 'positional'],
            new Autodiscover(arguments: ['mode' => 'x', 0 => 'positional'])->arguments,
        );
    }

    public function testAutodiscoverEmptyArgumentsIsNull(): void
    {
        self::assertNull(new Autodiscover(arguments: [])->arguments);
    }

    public function testAutodiscoverScalarConfiguration(): void
    {
        $autodiscover = new Autodiscover(
            autowire: false,
            preload : true,
            factory : 'create',
        );

        self::assertFalse($autodiscover->autowire);
        self::assertTrue($autodiscover->preload);
        self::assertSame('create', $autodiscover->factory);
    }

    // ── Binding attributes ───────────────────────────────────────

    #[DataProvider('provideBindingAttributes')]
    public function testBindingAttributeDefaultsAndResolution(
        BindingAttribute $attribute,
        ServiceBinding   $expected,
    ): void {
        // @phpstan-ignore-next-line BindingAttribute documents $locked.
        self::assertFalse($attribute->locked);
        self::assertSame($expected, ServiceBinding::resolve($attribute));
    }

    public static function provideBindingAttributes(): \Generator
    {
        yield 'inline' => [new Inline, ServiceBinding::Inline];
        yield 'scoped' => [new Scoped, ServiceBinding::Scoped];
        yield 'shared' => [new Shared, ServiceBinding::Shared];
        yield 'unique' => [new Unique, ServiceBinding::Unique];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideBindingAttributeClasses')]
    public function testBindingAttributeLockedFlag(
        string $class,
    ): void {
        $attribute = new $class(locked: true);

        // @phpstan-ignore-next-line Dynamic binding attribute instance.
        self::assertTrue($attribute->locked);
    }

    /**
     * @return \Generator<string, array{class-string}>
     */
    public static function provideBindingAttributeClasses(): \Generator
    {
        yield 'inline' => [Inline::class];
        yield 'scoped' => [Scoped::class];
        yield 'shared' => [Shared::class];
        yield 'unique' => [Unique::class];
    }

    // ── Attribute reflection ─────────────────────────────────────

    /**
     * @param class-string $class
     */
    #[DataProvider('provideClassTargetedAttributes')]
    public function testAttributeTargetsClassOnly(
        string $class,
    ): void {
        $flags = $this->attributeFlags($class);

        self::assertSame(\Attribute::TARGET_CLASS, $flags);
    }

    /**
     * @return \Generator<string, array{class-string}>
     */
    public static function provideClassTargetedAttributes(): \Generator
    {
        yield 'alias' => [Alias::class];
        yield 'autodiscover' => [Autodiscover::class];
        yield 'inline' => [Inline::class];
        yield 'scoped' => [Scoped::class];
        yield 'shared' => [Shared::class];
        yield 'unique' => [Unique::class];
    }

    public function testTagAttributeIsRepeatable(): void
    {
        $flags = $this->attributeFlags(Tag::class);

        self::assertSame(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE, $flags);
    }

    /**
     * @param class-string $class
     */
    private function attributeFlags(
        string $class,
    ): int {
        $attributes = new \ReflectionClass($class)->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        return $attributes[0]->newInstance()->flags;
    }
}

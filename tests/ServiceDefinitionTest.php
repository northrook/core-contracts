<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Callback;
use Northrook\Contracts\Container\ServiceBinding;
use Northrook\Contracts\ContainerException;
use Northrook\Contracts\ContainerInterface;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\JSON;
use Northrook\Contracts\Service\Alias;
use Northrook\Contracts\Service\Autodiscover;
use Northrook\Contracts\Service\Inline;
use Northrook\Contracts\Service\Tag;
use Northrook\Contracts\ServiceDefinition;
use Northrook\Contracts\ServiceDefinition\CompiledServiceDefinition;
use Northrook\Contracts\Tests\Support\ServiceDefinitionAliasInterface;
use Northrook\Contracts\Tests\Support\ServiceDefinitionFactoryFixture;
use Northrook\Contracts\Tests\Support\ServiceDefinitionFixture;
use Northrook\Contracts\Tests\Support\ServiceDefinitionFixtureB;
use PHPUnit\Framework\TestCase;

final class ServiceDefinitionTest extends TestCase
{
    public function testRegisterAssignsCallbacksAndHonorsBinding(): void
    {
        $callback = Callback::staticMethod(ServiceDefinitionFixtureB::class, 'create');

        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            callbacks: [$callback],
            binding: ServiceBinding::Unique,
        );

        self::assertSame(ServiceBinding::Unique, $definition->binding);
        self::assertCount(1, $definition->callbacks);
        self::assertTrue($definition->hasCallback($callback));
    }

    public function testRegisterNormalizesAutodiscover(): void
    {
        $attributes = new \ReflectionClass(ServiceDefinitionFixture::class)->getAttributes(Autodiscover::class);

        self::assertCount(1, $attributes);

        /** @var Autodiscover $autodiscover */
        $autodiscover = $attributes[0]->newInstance();

        $definition = ServiceDefinition::register(
            ServiceDefinitionFixture::class,
            $autodiscover,
        );

        self::assertSame(ServiceBinding::Unique, $definition->binding);
        self::assertFalse($definition->autowire);
        self::assertTrue($definition->preload);
        self::assertSame('create', $definition->factory);
        self::assertTrue($definition->hasAlias(ServiceDefinitionAliasInterface::class));
        self::assertTrue($definition->hasTag('fixture.tag'));
        self::assertTrue($definition->hasTag('fixture.extra'));
        self::assertSame(['arg'], $definition->getTag('fixture.extra')?->arguments);
        self::assertTrue($definition->hasArgument('mode'));
        self::assertSame('attribute', $definition->getArgument('mode'));
        self::assertTrue($definition->hasTag(ContainerInterface::DEFAULT_REFERENCE));
        self::assertSame(
            ['mode' => 'attribute'],
            $definition->getTag(ContainerInterface::DEFAULT_REFERENCE)?->arguments,
        );
    }

    public function testAliasAddHasRemove(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->addAlias(ServiceDefinitionAliasInterface::class);

        self::assertTrue($definition->hasAlias(ServiceDefinitionAliasInterface::class));
        self::assertContains(ServiceDefinitionAliasInterface::class, $definition->aliases);

        $definition->removeAlias(ServiceDefinitionAliasInterface::class);

        self::assertFalse($definition->hasAlias(ServiceDefinitionAliasInterface::class));
        self::assertSame([], $definition->aliases);
    }

    public function testDuplicateAliasThrows(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);
        $definition->addAlias(ServiceDefinitionAliasInterface::class);

        $this->expectException(ContainerException::class);
        $definition->addAlias(ServiceDefinitionAliasInterface::class);
    }

    public function testSetAliasesAcceptsAliasObject(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);
        $definition->setAliases(Alias::from(ServiceDefinitionAliasInterface::class));

        self::assertSame(
            [ServiceDefinitionAliasInterface::class],
            $definition->aliases,
        );
    }

    public function testTagAddHasGetRemove(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->addTag('role.logger', 'channel');

        $tag = $definition->getTag('role.logger');

        self::assertTrue($definition->hasTag('role.logger'));
        self::assertInstanceOf(Tag::class, $tag);
        self::assertSame(['channel'], $tag->arguments);

        $definition->removeTag('role.logger');

        self::assertFalse($definition->hasTag('role.logger'));
        self::assertNull($definition->getTag('role.logger'));
    }

    public function testDuplicateTagThrows(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);
        $definition->addTag('role.once');

        $this->expectException(ContainerException::class);
        $definition->addTag('role.once');
    }

    public function testCallbackAddHasGetRemove(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);
        $first      = Callback::staticMethod(ServiceDefinitionFixtureB::class, 'create');
        $second     = Callback::function(
            'strtolower',
            'X',
        );

        $definition->addCallback($first)->addCallback($second);

        self::assertTrue($definition->hasCallback($first));
        self::assertTrue($definition->hasCallback(
            Callback::staticMethod(ServiceDefinitionFixtureB::class, 'create'),
        ));
        self::assertSame($first, $definition->getCallback(0));
        self::assertSame($second, $definition->getCallback(1));

        $definition->removeCallback(0);

        self::assertSame($second, $definition->getCallback(0));
        self::assertNull($definition->getCallback(1));
    }

    public function testRemoveCallbackInvalidIndexThrows(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $this->expectException(ContainerException::class);
        $definition->removeCallback(0);
    }

    public function testLockedBlocksMutationIncludingBinding(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);
        $definition->lock();

        try {
            $definition->autowire(false);
            self::fail('Expected ContainerException for autowire()');
        } catch (ContainerException) {
        }

        try {
            $definition->binding(ServiceBinding::Inline);
            self::fail('Expected ContainerException for binding()');
        } catch (ContainerException) {
        }

        try {
            $definition->addAlias(ServiceDefinitionAliasInterface::class);
            self::fail('Expected ContainerException for addAlias()');
        } catch (ContainerException) {
        }

        try {
            $definition->setArgument('mode', 'x');
            self::fail('Expected ContainerException for setArgument()');
        } catch (ContainerException) {
        }

        $definition->lock(false);
        $definition->binding(ServiceBinding::Inline);

        self::assertSame(ServiceBinding::Inline, $definition->binding);
    }

    public function testClearFactory(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            factory: 'create',
        );

        self::assertSame('create', $definition->factory);

        $definition->clearFactory();

        self::assertFalse($definition->factory);
    }

    public function testRegisterAllowsDuplicateClassIds(): void
    {
        $first  = ServiceDefinition::register(ServiceDefinitionFixtureB::class);
        $second = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        self::assertSame($first->id, $second->id);
        self::assertNotSame($first, $second);
    }

    public function testSetFactoryAcceptsMatchingClassMethodString(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->setFactory(ServiceDefinitionFixtureB::class . '::create');

        self::assertSame('create', $definition->factory);
    }

    public function testSetFactoryRejectsMismatchedClassMethodString(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $this->expectException(ContainerException::class);
        $definition->setFactory(ServiceDefinitionFixture::class . '::create');
    }

    public function testExportShape(): void
    {
        $callback = Callback::staticMethod(ServiceDefinitionFixtureB::class, 'create');

        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            aliases: [ServiceDefinitionAliasInterface::class],
            tags: ['export.tag'],
            preload: true,
            factory: 'create',
            callbacks: [$callback],
            binding: ServiceBinding::Scoped,
        );

        $export = $definition->export();

        self::assertSame($definition->id, $export['id']);
        self::assertSame(ServiceDefinitionFixtureB::class, $export['class']);
        self::assertSame('scoped', $export['binding']);
        self::assertSame([ServiceDefinitionAliasInterface::class], $export['aliases']);
        self::assertSame(
            [['reference' => 'export.tag', 'arguments' => null]],
            $export['tags'],
        );
        self::assertTrue($export['autowire']);
        self::assertTrue($export['preload']);
        self::assertSame('create', $export['factory']);
        self::assertSame([$callback->__serialize()], $export['callbacks']);
        self::assertFalse($export['locked']);
    }

    public function testFinalizeLocksAndReturnsCompiledDefinition(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            aliases: [ServiceDefinitionAliasInterface::class],
            tags: [['compiled.tag', 1]],
            binding: ServiceBinding::Unique,
        );

        $compiled = $definition->finalize();

        self::assertTrue($definition->locked);
        self::assertInstanceOf(CompiledServiceDefinition::class, $compiled);
        self::assertTrue($compiled->locked);
        self::assertSame($definition->export(), $compiled->toArray());
        self::assertSame(ServiceBinding::Unique, $compiled->binding);
        self::assertSame([ServiceDefinitionAliasInterface::class], $compiled->aliases);
        self::assertTrue($compiled->tags[0]->reference === 'compiled.tag');
    }

    public function testCompiledDefinitionJsonRoundTripViaToArray(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            aliases: [ServiceDefinitionAliasInterface::class],
            tags: ['json.tag'],
            callbacks: [Callback::function(
                'strtolower',
                'A',
            )],
            binding: ServiceBinding::Shared,
        );

        $compiled = $definition->finalize();
        $json     = JSON::encode($compiled->toArray());
        $decoded  = JSON::decode($json);

        self::assertSame($compiled->toArray(), $decoded);
        self::assertSame($definition->export(), $decoded);
    }

    public function testIdIsLowercaseDottedFqcn(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        self::assertSame(
            'northrook.contracts.tests.support.servicedefinitionfixtureb',
            $definition->id,
        );
    }

    public function testRegisterDefaults(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        self::assertSame(ServiceBinding::Shared, $definition->binding);
        self::assertTrue($definition->autowire);
        self::assertFalse($definition->preload);
        self::assertFalse($definition->factory);
        self::assertFalse($definition->locked);
        self::assertSame([], $definition->aliases);
        self::assertSame([], $definition->tags);
        self::assertSame([], $definition->callbacks);
        self::assertFalse($definition->hasTag(ContainerInterface::DEFAULT_REFERENCE));
        self::assertFalse($definition->hasArgument('mode'));
    }

    public function testRegisterUnknownClassThrows(): void
    {
        $this->expectException(ContainerException::class);
        // @phpstan-ignore-next-line Testing invalid input.
        ServiceDefinition::register('Northrook\Contracts\Tests\Support\DoesNotExist');
    }

    public function testRegisterLockedBlocksMutationImmediately(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            locked: true,
        );

        self::assertTrue($definition->locked);

        $this->expectException(ContainerException::class);
        $definition->autowire(false);
    }

    public function testAutodiscoverValuesTakePrecedenceOverExplicitArguments(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            autodiscover: new Autodiscover(binding: ServiceBinding::Inline),
            binding: ServiceBinding::Scoped,
        );

        self::assertSame(ServiceBinding::Inline, $definition->binding);
    }

    public function testBindingAcceptsStringAndLockFlag(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->binding('scoped', lock: true);

        self::assertSame(ServiceBinding::Scoped, $definition->binding);
        self::assertTrue($definition->locked);

        $this->expectException(ContainerException::class);
        $definition->preload();
    }

    public function testBindingAcceptsBindingAttributeInstance(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->binding(new Inline);

        self::assertSame(ServiceBinding::Inline, $definition->binding);
    }

    public function testBindingRejectsInvalidString(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $this->expectException(InvalidArgumentException::class);
        $definition->binding('bogus');
    }

    public function testAutowireAndPreloadToggle(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->autowire(false)->preload();

        self::assertFalse($definition->autowire);
        self::assertTrue($definition->preload);

        $definition->autowire()->preload(false);

        self::assertTrue($definition->autowire);
        self::assertFalse($definition->preload);
    }

    public function testSetAliasesFromStringAndEmpty(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->setAliases(ServiceDefinitionAliasInterface::class);

        self::assertSame([ServiceDefinitionAliasInterface::class], $definition->aliases);

        // @phpstan-ignore-next-line Empty string clears aliases (not a class-string).
        $definition->setAliases('');

        self::assertSame([], $definition->aliases);
    }

    public function testAddAliasAcceptsAliasObjectAndMultiple(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->addAlias(Alias::from(ServiceDefinitionAliasInterface::class));
        $definition->addAlias(ServiceDefinitionFixture::class, ServiceDefinitionFixtureB::class);

        self::assertSame(
            [
                ServiceDefinitionAliasInterface::class,
                ServiceDefinitionFixture::class,
                ServiceDefinitionFixtureB::class,
            ],
            \array_values($definition->aliases),
        );
    }

    public function testRemoveUnknownAliasIsNoOp(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            aliases: [ServiceDefinitionAliasInterface::class],
        );

        $definition->removeAlias(ServiceDefinitionFixture::class);

        self::assertSame([ServiceDefinitionAliasInterface::class], $definition->aliases);
    }

    public function testSetTagsRejectsDuplicateReference(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $this->expectException(ContainerException::class);
        $definition->setTags(['dup.tag', ['dup.tag', 'arg']]);
    }

    public function testSetTagsAcceptsTagInstancesAndReplaces(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            tags: ['old.tag'],
        );

        $tag = new Tag('new.tag', 'arg');
        $definition->setTags([$tag]);

        self::assertFalse($definition->hasTag('old.tag'));
        self::assertSame([$tag], $definition->tags);
    }

    public function testAddTagArrayForm(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->addTag(['array.tag', 'arg']);

        self::assertTrue($definition->hasTag('array.tag'));
        self::assertSame(['arg'], $definition->getTag('array.tag')?->arguments);
    }

    public function testSetCallbacksReplacesExisting(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            callbacks: [Callback::function(
                'strtolower',
                'A',
            )],
        );

        $replacement = Callback::staticMethod(ServiceDefinitionFixtureB::class, 'create');
        $definition->setCallbacks($replacement);

        self::assertCount(1, $definition->callbacks);
        self::assertSame($replacement, $definition->getCallback(0));
        self::assertNull($definition->getCallback(1));
    }

    public function testSetFactoryAcceptsArrayForm(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->setFactory([ServiceDefinitionFixtureB::class, 'create']);

        self::assertSame('create', $definition->factory);
    }

    public function testSetFactoryRejectsArrayWithMismatchedClass(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $this->expectException(ContainerException::class);
        $definition->setFactory([ServiceDefinitionFixture::class, 'create']);
    }

    public function testSetFactoryRejectsMissingMethod(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFactoryFixture::class);

        $this->expectException(ContainerException::class);
        $definition->setFactory('missingFactory');
    }

    public function testSetFactoryRejectsNonPublicMethod(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFactoryFixture::class);

        $this->expectException(ContainerException::class);
        $definition->setFactory('protectedCreate');
    }

    public function testSetFactoryRejectsNonStaticMethod(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFactoryFixture::class);

        $this->expectException(ContainerException::class);
        $definition->setFactory('instanceCreate');
    }

    public function testFinalizeOnAlreadyLockedDefinition(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            locked: true,
        );

        $compiled = $definition->finalize();

        self::assertTrue($definition->locked);
        self::assertTrue($compiled->locked);
        self::assertSame($definition->export(), $compiled->toArray());
    }

    public function testCompiledDefinitionExposesDefinitionState(): void
    {
        $callback = Callback::function(
            'strtolower',
            'A',
        );

        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            autowire: false,
            preload: true,
            factory: 'create',
            callbacks: [$callback],
        );

        $compiled = $definition->finalize();

        self::assertSame($definition->id, $compiled->id);
        self::assertSame(ServiceDefinitionFixtureB::class, $compiled->class);
        self::assertFalse($compiled->autowire);
        self::assertTrue($compiled->preload);
        self::assertSame('create', $compiled->factory);
        self::assertSame([$callback], $compiled->callbacks);
    }

    public function testExportReflectsLockedState(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        self::assertFalse($definition->export()['locked']);

        $definition->lock();

        self::assertTrue($definition->export()['locked']);
    }

    public function testArgumentAddHasGetSetRemoveClear(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        $definition->setArguments(['mode' => 'a', 0 => 'first']);

        self::assertTrue($definition->hasTag(ContainerInterface::DEFAULT_REFERENCE));
        self::assertTrue($definition->hasArgument('mode'));
        self::assertTrue($definition->hasArgument(0));
        self::assertSame('a', $definition->getArgument('mode'));
        self::assertSame('first', $definition->getArgument(0));
        self::assertSame(
            [0 => 'first', 'mode' => 'a'],
            $definition->getTag(ContainerInterface::DEFAULT_REFERENCE)?->arguments,
        );

        $definition->setArgument('mode', 'b');
        $definition->addArgument('channel', 'app');

        self::assertSame('b', $definition->getArgument('mode'));
        self::assertSame('app', $definition->getArgument('channel'));

        $definition->removeArgument(0);

        self::assertFalse($definition->hasArgument(0));
        self::assertNull($definition->getArgument(0));
        self::assertTrue($definition->hasArgument('mode'));

        $definition->setArgument('nullable', null);

        self::assertTrue($definition->hasArgument('nullable'));
        self::assertNull($definition->getArgument('nullable'));

        $definition->clearArguments();

        self::assertFalse($definition->hasTag(ContainerInterface::DEFAULT_REFERENCE));
        self::assertFalse($definition->hasArgument('mode'));
    }

    public function testSetArgumentsEmptyRemovesReservedTag(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            arguments: ['mode' => 'x'],
        );

        self::assertTrue($definition->hasTag(ContainerInterface::DEFAULT_REFERENCE));

        $definition->setArguments([]);

        self::assertFalse($definition->hasTag(ContainerInterface::DEFAULT_REFERENCE));
    }

    public function testRemoveArgumentClearsReservedTagWhenEmpty(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            arguments: ['mode' => 'x'],
        );

        $definition->removeArgument('mode');

        self::assertFalse($definition->hasTag(ContainerInterface::DEFAULT_REFERENCE));
    }

    public function testDuplicateAddArgumentThrows(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);
        $definition->addArgument('mode', 'a');

        $this->expectException(ContainerException::class);
        $definition->addArgument('mode', 'b');
    }

    public function testInvalidArgumentKeysThrow(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);

        try {
            // @phpstan-ignore-next-line Testing invalid input.
            $definition->setArgument('', 'x');
            self::fail('Expected InvalidArgumentException for empty key');
        } catch (InvalidArgumentException) {
        }

        try {
            // @phpstan-ignore-next-line Testing invalid input.
            $definition->setArgument('$mode', 'x');
            self::fail('Expected InvalidArgumentException for $-prefixed key');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line Testing invalid input.
        $definition->setArgument(-1, 'x');
    }

    public function testAddTagDefaultReferenceAfterArgumentsThrows(): void
    {
        $definition = ServiceDefinition::register(ServiceDefinitionFixtureB::class);
        $definition->setArguments(['mode' => 'x']);

        $this->expectException(ContainerException::class);
        $definition->addTag(ContainerInterface::DEFAULT_REFERENCE, 'y');
    }

    public function testRegisterArgumentsMaterializeReservedTag(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            arguments: ['mode' => 'register'],
        );

        self::assertTrue($definition->hasArgument('mode'));
        self::assertSame('register', $definition->getArgument('mode'));
        self::assertSame(
            ['mode' => 'register'],
            $definition->getTag(ContainerInterface::DEFAULT_REFERENCE)?->arguments,
        );
    }

    public function testAutodiscoverArgumentsTakePrecedenceOverExplicit(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            autodiscover: new Autodiscover(arguments: ['mode' => 'attribute']),
            arguments: ['mode' => 'explicit'],
        );

        self::assertSame('attribute', $definition->getArgument('mode'));
    }

    public function testExportAndFinalizeIncludeReservedArgumentTag(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            arguments: ['mode' => 'export'],
            tags: ['other.tag'],
        );

        $compiled = $definition->finalize();
        $export   = $definition->export();

        self::assertTrue($definition->locked);
        self::assertSame($export, $compiled->toArray());
        self::assertContains(
            [
                'reference' => ContainerInterface::DEFAULT_REFERENCE,
                'arguments' => ['mode' => 'export'],
            ],
            $export['tags'],
        );
        self::assertContains(
            [
                'reference' => 'other.tag',
                'arguments' => null,
            ],
            $export['tags'],
        );
    }

    public function testCompiledArgumentTagJsonRoundTrip(): void
    {
        $definition = ServiceDefinition::register(
            ServiceDefinitionFixtureB::class,
            arguments: ['mode' => 'json', 1 => 'pos'],
        );

        $compiled = $definition->finalize();
        $json     = JSON::encode($compiled->toArray());
        $decoded  = JSON::decode($json);

        self::assertSame($compiled->toArray(), $decoded);
        self::assertSame($definition->export(), $decoded);
    }
}

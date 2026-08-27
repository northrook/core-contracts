<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\ColorScheme;
use Northrook\Context;
use Northrook\Context\AppDebug;
use Northrook\Context\AppEnv;
use Northrook\Context\ContextEntry;
use Northrook\Context\ContextManager;
use Northrook\Contracts\Tests\Support\ResetsContext;
use Northrook\InvalidArgumentException;
use Northrook\Kernel\KernelContext;
use Northrook\LogicException;
use Northrook\Timestamp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextManagerTest extends TestCase
{
    protected function setUp(): void
    {
        self::resetIsolation();
    }

    protected function tearDown(): void
    {
        self::resetIsolation();
    }

    // -------------------------------------------------------------------------
    // Construction / uniqueness
    // -------------------------------------------------------------------------

    public function testConstructDefaults(): void
    {
        $manager = $this->manager();

        self::assertFalse($manager->frozen);
        self::assertSame([], $manager->current);
        self::assertSame([], $manager->history);
        self::assertInstanceOf(ContextManager::class, $manager);
    }

    public function testSecondConstructWhileAliveThrows(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot instantiate multiple ContextManagers.');

        new ContextManager;
    }

    public function testConstructAfterReleaseAndInitializedReset(): void
    {
        $first = $this->manager();
        unset($first);
        self::resetInitialized();

        $second = new ContextManager;

        self::assertFalse($second->frozen);
        self::assertSame([], $second->current);
    }

    public function testReflectionCanClearInitializedWhileInstanceRetained(): void
    {
        $retained = $this->manager();
        self::resetInitialized();

        $second = new ContextManager;

        self::assertNotSame($retained, $second);
        self::assertFalse($second->frozen);
    }

    // -------------------------------------------------------------------------
    // entry / has / hasAll / hasAny
    // -------------------------------------------------------------------------

    public function testEntryNullWhenUnset(): void
    {
        $manager = $this->manager();

        self::assertNull($manager->entry(KernelContext::class));
        self::assertNull($manager->entry(KernelContext::Boot));
        self::assertNull($manager->entry('NotARealContextEnum'));
    }

    public function testEntryByClassStringAndCaseIgnoresCaseForLookup(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);

        $byClass = $manager->entry(KernelContext::class);
        $byCase  = $manager->entry(KernelContext::Request);

        self::assertInstanceOf(ContextEntry::class, $byClass);
        self::assertSame($byClass, $byCase);
        self::assertSame(KernelContext::Boot, $byClass->context);
        self::assertSame(KernelContext::class, $byClass->key);
        self::assertInstanceOf(Timestamp::class, $byClass->timestamp);
    }

    public function testHasClassStringVersusExactCase(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);

        self::assertTrue($manager->has(KernelContext::class));
        self::assertTrue($manager->has(KernelContext::Boot));
        self::assertFalse($manager->has(KernelContext::Request));
        self::assertFalse($manager->has(ColorScheme::class));
        self::assertFalse($manager->has('NotARealContextEnum'));
    }

    public function testHasAllAndHasAny(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot, ColorScheme::Light);

        self::assertTrue($manager->hasAll(KernelContext::Boot, ColorScheme::Light));
        self::assertTrue($manager->hasAll(KernelContext::class, ColorScheme::Light));
        self::assertFalse($manager->hasAll(KernelContext::Boot, ColorScheme::Dark));
        self::assertFalse($manager->hasAll(KernelContext::Request));

        self::assertTrue($manager->hasAny(KernelContext::Request, ColorScheme::Light));
        self::assertFalse($manager->hasAny(KernelContext::Request, ColorScheme::Dark));
    }

    public function testHasAllAndHasAnyEmptyArgsReturnFalse(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);

        self::assertFalse($manager->hasAll());
        self::assertFalse($manager->hasAny());
    }

    public function testHasAllAndHasAnyMixedClassStringAndCase(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Runtime, ColorScheme::Dark);

        self::assertTrue($manager->hasAll(KernelContext::class, ColorScheme::Dark));
        self::assertTrue($manager->hasAny(KernelContext::Boot, ColorScheme::class));
        self::assertFalse($manager->hasAll(KernelContext::Boot, ColorScheme::class));
    }

    // -------------------------------------------------------------------------
    // replace
    // -------------------------------------------------------------------------

    public function testReplaceFirstWriteReturnsNullAndCreatesEntry(): void
    {
        $manager = $this->manager();

        self::assertNull($manager->replace(KernelContext::Boot));

        $entry = $manager->entry(KernelContext::class);
        self::assertInstanceOf(ContextEntry::class, $entry);
        self::assertSame(KernelContext::Boot, $entry->context);
        self::assertSame(KernelContext::class, $entry->key);
        self::assertSame([], $manager->history);
    }

    public function testReplaceDifferentCaseReturnsPreviousAndPushesHistory(): void
    {
        $manager = $this->manager();
        $manager->replace(KernelContext::Boot);
        $previous = $manager->entry(KernelContext::class);
        self::assertInstanceOf(ContextEntry::class, $previous);

        $returned = $manager->replace(KernelContext::Request);

        self::assertSame(KernelContext::Boot, $returned);
        self::assertSame(KernelContext::Request, $manager->entry(KernelContext::class)?->context);
        self::assertCount(1, $manager->history);
        self::assertSame($previous, $manager->history[0]);
    }

    public function testReplaceIdenticalCaseIsNoOpRetainingEntry(): void
    {
        $manager = $this->manager();
        $manager->replace(KernelContext::Boot);
        $entry = $manager->entry(KernelContext::class);
        self::assertInstanceOf(ContextEntry::class, $entry);

        $returned = $manager->replace(KernelContext::Boot);

        self::assertSame(KernelContext::Boot, $returned);
        self::assertSame($entry, $manager->entry(KernelContext::class));
        self::assertSame([], $manager->history);
    }

    public function testReplaceDoesNotAffectOtherEnumClasses(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot, ColorScheme::Light);

        $manager->replace(KernelContext::Request);

        self::assertSame(KernelContext::Request, $manager->entry(KernelContext::class)?->context);
        self::assertSame(ColorScheme::Light, $manager->entry(ColorScheme::class)?->context);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function testUpdateZeroArgsIsNoOp(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);
        $entry = $manager->entry(KernelContext::class);

        $manager->update();

        self::assertSame($entry, $manager->entry(KernelContext::class));
        self::assertSame([], $manager->history);
    }

    public function testUpdateInsertsAndChangesDistinctClasses(): void
    {
        $manager = $this->manager();

        $manager->update(KernelContext::Boot, ColorScheme::Light);
        self::assertTrue($manager->hasAll(KernelContext::Boot, ColorScheme::Light));

        $boot = $manager->entry(KernelContext::class);
        $manager->update(KernelContext::Runtime);

        self::assertSame(KernelContext::Runtime, $manager->entry(KernelContext::class)?->context);
        self::assertSame(ColorScheme::Light, $manager->entry(ColorScheme::class)?->context);
        self::assertCount(1, $manager->history);
        self::assertSame($boot, $manager->history[0]);
    }

    public function testUpdateIdenticalCaseSkipped(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);
        $entry = $manager->entry(KernelContext::class);

        $manager->update(KernelContext::Boot);

        self::assertSame($entry, $manager->entry(KernelContext::class));
        self::assertSame([], $manager->history);
    }

    public function testUpdateDuplicateEnumClassThrows(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('update');
        $this->expectExceptionMessage('unique Context entries');

        $manager->update(KernelContext::Boot, KernelContext::Request);
    }

    public function testUpdateTwoDistinctClassesOk(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Runtime, ColorScheme::Dark);

        self::assertTrue($manager->hasAll(KernelContext::Runtime, ColorScheme::Dark));
    }

    // -------------------------------------------------------------------------
    // set
    // -------------------------------------------------------------------------

    public function testSetReplacesMapAndDisplacesOmitted(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot, ColorScheme::Light);
        $scheme = $manager->entry(ColorScheme::class);
        self::assertInstanceOf(ContextEntry::class, $scheme);

        $manager->set(KernelContext::Request);

        self::assertTrue($manager->has(KernelContext::Request));
        self::assertFalse($manager->has(ColorScheme::class));
        self::assertContains($scheme, $manager->history);
    }

    public function testSetSameCaseRetainsEntryInstance(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot, ColorScheme::Light);
        $boot = $manager->entry(KernelContext::class);
        self::assertInstanceOf(ContextEntry::class, $boot);

        $manager->set(KernelContext::Boot);

        self::assertSame($boot, $manager->entry(KernelContext::class));
        self::assertFalse($manager->has(ColorScheme::class));
        self::assertNotContains($boot, $manager->history);
    }

    public function testSetChangedCasePushesOldToHistory(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);
        $boot = $manager->entry(KernelContext::class);
        self::assertInstanceOf(ContextEntry::class, $boot);

        $manager->set(KernelContext::Request);

        self::assertSame(KernelContext::Request, $manager->entry(KernelContext::class)?->context);
        self::assertSame([$boot], $manager->history);
    }

    public function testEmptySetEquivalentToClear(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot, ColorScheme::Light);
        $boot   = $manager->entry(KernelContext::class);
        $scheme = $manager->entry(ColorScheme::class);

        $manager->set();

        self::assertSame([], $manager->current);
        self::assertSame([$scheme, $boot], $manager->history);
    }

    public function testSetDuplicateEnumClassThrows(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('set');
        $this->expectExceptionMessage('unique Context entries');

        $manager->set(KernelContext::Boot, KernelContext::Request);
    }

    public function testSetHistoryOrderChangedThenOmitted(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot, ColorScheme::Light);
        $boot   = $manager->entry(KernelContext::class);
        $scheme = $manager->entry(ColorScheme::class);
        self::assertInstanceOf(ContextEntry::class, $boot);
        self::assertInstanceOf(ContextEntry::class, $scheme);

        // Change KernelContext; omit ColorScheme → push changed first, then omitted.
        $manager->set(KernelContext::Request);

        self::assertSame([$scheme, $boot], $manager->history);
        self::assertSame($scheme, $manager->history[0]);
        self::assertSame($boot, $manager->history[1]);
    }

    // -------------------------------------------------------------------------
    // unset
    // -------------------------------------------------------------------------

    public function testUnsetByClassStringRemovesRegardlessOfCase(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);
        $entry = $manager->entry(KernelContext::class);

        $manager->unset(KernelContext::class);

        self::assertFalse($manager->has(KernelContext::class));
        self::assertSame([$entry], $manager->history);
    }

    public function testUnsetMatchingCaseRemoves(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);
        $entry = $manager->entry(KernelContext::class);

        $manager->unset(KernelContext::Boot);

        self::assertFalse($manager->has(KernelContext::class));
        self::assertSame([$entry], $manager->history);
    }

    public function testUnsetNonMatchingCaseIsNoOp(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);
        $entry = $manager->entry(KernelContext::class);

        $manager->unset(KernelContext::Request);

        self::assertSame($entry, $manager->entry(KernelContext::class));
        self::assertSame([], $manager->history);
    }

    public function testUnsetMissingKeyIsNoOp(): void
    {
        $manager = $this->manager();
        $manager->unset(KernelContext::class);
        $manager->unset(KernelContext::Boot);

        self::assertSame([], $manager->current);
        self::assertSame([], $manager->history);
    }

    public function testUnsetMultipleArgsPartialNoOps(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot, ColorScheme::Light);
        $boot = $manager->entry(KernelContext::class);

        $manager->unset(KernelContext::Request, ColorScheme::Light, KernelContext::Boot);

        self::assertFalse($manager->has(KernelContext::class));
        self::assertFalse($manager->has(ColorScheme::class));
        self::assertCount(2, $manager->history);
        self::assertContains($boot, $manager->history);
    }

    // -------------------------------------------------------------------------
    // clear vs reset
    // -------------------------------------------------------------------------

    public function testClearDisplacesIntoHistory(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot, ColorScheme::Light);
        $boot   = $manager->entry(KernelContext::class);
        $scheme = $manager->entry(ColorScheme::class);

        $manager->clear();

        self::assertSame([], $manager->current);
        self::assertSame([$scheme, $boot], $manager->history);
    }

    public function testResetClearsMapAndHistory(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);
        $manager->replace(KernelContext::Request);

        $manager->reset();

        self::assertSame([], $manager->current);
        self::assertSame([], $manager->history);
    }

    public function testResetDiscardsPriorHistoryUnlikeClear(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot);
        $manager->replace(KernelContext::Request);
        self::assertNotSame([], $manager->history);

        $manager->reset();
        self::assertSame([], $manager->history);

        $manager->update(KernelContext::Runtime);
        $manager->clear();
        self::assertCount(1, $manager->history);
    }

    // -------------------------------------------------------------------------
    // History / capacity / current
    // -------------------------------------------------------------------------

    public function testHistoryMostRecentFirstAfterSequentialReplaces(): void
    {
        $manager = $this->manager();
        $manager->replace(KernelContext::Boot);
        $boot = $manager->entry(KernelContext::class);
        $manager->replace(KernelContext::Compile);
        $compile = $manager->entry(KernelContext::class);
        $manager->replace(KernelContext::Runtime);

        self::assertSame([$compile, $boot], $manager->history);
        self::assertSame($compile, $manager->history[0]);
    }

    public function testHistoryCapTwelveOverwritesOldest(): void
    {
        $manager = $this->manager();
        $manager->replace(KernelContext::Boot);
        $first = $manager->entry(KernelContext::class);
        self::assertInstanceOf(ContextEntry::class, $first);

        // Advance once, then stay in the runtime band (order 2) — never regress to Boot/Compile.
        $cases = [
            KernelContext::Compile,
            KernelContext::Runtime,
            KernelContext::Request,
            KernelContext::Response,
            KernelContext::Runtime,
            KernelContext::Request,
            KernelContext::Response,
            KernelContext::Runtime,
            KernelContext::Request,
            KernelContext::Response,
            KernelContext::Runtime,
            KernelContext::Request,
            KernelContext::Response,
        ];

        foreach ($cases as $case) {
            $manager->replace($case);
        }

        // 13 displacements from the 13 replaces after the initial Boot.
        self::assertCount(12, $manager->history);
        self::assertNotContains($first, $manager->history);
        self::assertSame(KernelContext::Response, $manager->entry(KernelContext::class)?->context);
    }

    public function testCurrentReflectsActiveInsertionOrder(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot, ColorScheme::Light);

        $current = $manager->current;
        self::assertCount(2, $current);
        self::assertSame(KernelContext::Boot, $current[0]->context);
        self::assertSame(ColorScheme::Light, $current[1]->context);

        $manager->set(ColorScheme::Dark, KernelContext::Runtime);
        $current = $manager->current;
        self::assertCount(2, $current);
        self::assertSame(ColorScheme::Dark, $current[0]->context);
        self::assertSame(KernelContext::Runtime, $current[1]->context);
    }

    public function testNoOpWritesRetainSameContextEntryObject(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Boot, ColorScheme::Light);
        $boot   = $manager->entry(KernelContext::class);
        $scheme = $manager->entry(ColorScheme::class);

        $manager->replace(KernelContext::Boot);
        $manager->update(ColorScheme::Light);
        $manager->set(KernelContext::Boot, ColorScheme::Light);

        self::assertSame($boot, $manager->entry(KernelContext::class));
        self::assertSame($scheme, $manager->entry(ColorScheme::class));
        self::assertSame([], $manager->history);
    }

    // -------------------------------------------------------------------------
    // KernelContext order guard
    // -------------------------------------------------------------------------

    public function testKernelContextMayAdvanceBootCompileRuntime(): void
    {
        $manager = $this->manager();

        $manager->replace(KernelContext::Boot);
        $manager->replace(KernelContext::Compile);
        $manager->replace(KernelContext::Runtime);

        self::assertTrue($manager->has(KernelContext::Runtime));
        self::assertCount(2, $manager->history);
    }

    public function testKernelContextMayMoveLaterallyWithinRuntimeBand(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Runtime);

        $manager->replace(KernelContext::Request);
        $manager->replace(KernelContext::Response);
        $manager->replace(KernelContext::Runtime);

        self::assertTrue($manager->has(KernelContext::Runtime));
    }

    public function testKernelContextRejectsRegressionToBoot(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);
        $manager->update(KernelContext::Runtime);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid KernelContext case order.');

        $manager->replace(KernelContext::Boot);
    }

    public function testKernelContextRejectsRegressionToCompile(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);
        $manager->update(KernelContext::Request);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid KernelContext case order.');

        $manager->update(KernelContext::Compile);
    }

    public function testKernelContextRejectsCompileToBootViaSet(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);
        $manager->update(KernelContext::Compile);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid KernelContext case order.');

        $manager->set(KernelContext::Boot);
    }

    public function testKernelContextGuardAllowsIdenticalCase(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Compile);
        $entry = $manager->entry(KernelContext::class);

        $manager->update(KernelContext::Compile);
        $manager->set(KernelContext::Compile);

        self::assertSame($entry, $manager->entry(KernelContext::class));
    }

    public function testKernelContextMayBeReintroducedAfterUnset(): void
    {
        $manager = $this->manager();
        $manager->update(KernelContext::Request);
        $manager->unset(KernelContext::class);

        $manager->update(KernelContext::Boot);
        self::assertTrue($manager->has(KernelContext::Boot));
    }

    public function testNonKernelContextIgnoresOrderGuard(): void
    {
        $manager = $this->manager();
        $manager->update(ColorScheme::Dark);
        $manager->update(ColorScheme::Light);

        self::assertTrue($manager->has(ColorScheme::Light));
    }

    // -------------------------------------------------------------------------
    // Freeze / editable / tripwire
    // -------------------------------------------------------------------------

    public function testFreezeSetsFrozenIdempotent(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);

        $manager->freeze();
        self::assertTrue($manager->frozen);

        $manager->freeze(true);
        self::assertTrue($manager->frozen);
    }

    public function testFreezeItselfNotBlockedWhenAlreadyFrozen(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);

        $manager->freeze();
        $manager->freeze(true);

        self::assertTrue($manager->frozen);
    }

    #[DataProvider('provideFrozenMutators')]
    public function testFrozenBlocksMutators(
        callable $mutate,
    ): void {
        $manager = $this->manager();
        $this->registerTrusted($manager);
        $manager->update(KernelContext::Boot);
        $manager->freeze();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot modify frozen context.');

        $mutate($manager);
    }

    /**
     * @return \Generator<string, array{callable(ContextManager): void}>
     */
    public static function provideFrozenMutators(): \Generator
    {
        yield 'replace' => [static fn(ContextManager $m) => $m->replace(KernelContext::Request)];
        yield 'replace identical no-op' => [static fn(ContextManager $m) => $m->replace(KernelContext::Boot)];
        yield 'update' => [static fn(ContextManager $m) => $m->update(KernelContext::Request)];
        yield 'update identical no-op' => [static fn(ContextManager $m) => $m->update(KernelContext::Boot)];
        yield 'set' => [static fn(ContextManager $m) => $m->set(KernelContext::Request)];
        yield 'unset' => [static fn(ContextManager $m) => $m->unset(KernelContext::Boot)];
        yield 'clear' => [static fn(ContextManager $m) => $m->clear()];
        yield 'reset' => [static fn(ContextManager $m) => $m->reset()];
    }

    public function testTrustedUnfreezeAllowsMutationAgain(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);

        $manager->freeze();
        $manager->freeze(false);

        self::assertFalse($manager->frozen);
        $manager->update(KernelContext::Runtime);
        self::assertTrue($manager->has(KernelContext::Runtime));
    }

    public function testUnfreezeWhileRequestThrowsAndStaysFrozen(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);
        $manager->update(KernelContext::Request);
        $manager->freeze();

        try {
            $manager->freeze(false);
            self::fail('Expected LogicException');
        }
        catch (LogicException $exception) {
            self::assertStringContainsString('Cannot unfreeze context in an untrusted context.', $exception->getMessage());
        }

        self::assertTrue($manager->frozen);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot modify frozen context.');
        $manager->update(KernelContext::Runtime);
    }

    public function testUnfreezeWhileFailsafeThrows(): void
    {
        $manager = $this->manager();
        $this->registerContext($manager, AppEnv::Failsafe);
        $manager->freeze();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot unfreeze context in an untrusted context.');

        $manager->freeze(false);
    }

    public function testUnfreezeWhenAlreadyUnfrozenButUntrustedStillThrows(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);
        $manager->update(KernelContext::Request);

        self::assertFalse($manager->frozen);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot unfreeze context in an untrusted context.');

        $manager->freeze(false);
    }

    public function testFreezeWhileRequestThenUnfreezeIsPermanentLock(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);
        $manager->update(KernelContext::Request);
        $manager->freeze(true);

        self::assertTrue($manager->frozen);

        try {
            $manager->freeze(false);
            self::fail('Expected LogicException');
        }
        catch (LogicException $exception) {
            self::assertStringContainsString('Cannot unfreeze context in an untrusted context.', $exception->getMessage());
        }

        self::assertTrue($manager->frozen);
    }

    public function testUnfreezeAfterRequestCleared(): void
    {
        $manager = $this->manager();
        $this->registerTrusted($manager);
        $manager->update(KernelContext::Request);
        self::assertTrue(Context::isUntrusted());

        $manager->unset(KernelContext::class);
        self::assertFalse(Context::isUntrusted());

        $manager->freeze();
        $manager->freeze(false);
        self::assertFalse($manager->frozen);
        $manager->update(KernelContext::Runtime);
        self::assertTrue($manager->has(KernelContext::Runtime));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function manager(): ContextManager
    {
        self::resetIsolation();

        return new ContextManager;
    }

    private function registerTrusted(
        ContextManager $manager,
    ): void {
        $this->registerContext($manager, AppEnv::Testing);
    }

    private function registerContext(
        ContextManager $manager,
        AppEnv         $appEnv,
    ): void {
        Context::register(
            appEnv        : $appEnv,
            appDebug      : AppDebug::Disabled,
            contextManager: $manager,
        );
    }

    private static function resetIsolation(): void
    {
        ResetsContext::reset();
    }

    private static function resetInitialized(): void
    {
        $property = new \ReflectionProperty(ContextManager::class, 'initialized');
        $property->setValue(null, false);
    }
}

<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\AppEnv;
use Northrook\Contracts\AppEnvironment;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Value\Secret;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for {@see Secret} policy metadata (types, conditions, freeze, serialize).
 */
final class SecretTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->resetAppEnv();
        parent::tearDown();
    }

    // ── Construct / types ────────────────────────────────────────

    public function testConstructDefaultsUnfrozenSensitiveWithNoConditions(): void
    {
        $secret = new Secret(Secret::SENSITIVE);

        self::assertSame(Secret::SENSITIVE, $secret->type);
        self::assertSame([], $secret->conditions);
        self::assertFalse($secret->isFrozen());
    }

    public function testConstructAcceptsSingleConditionString(): void
    {
        $secret = new Secret(Secret::CREDENTIAL, 'db-dsn');

        self::assertTrue($secret->hasCondition('db-dsn'));
        self::assertSame(['db-dsn' => 'db-dsn'], $secret->conditions);
    }

    public function testConstructAcceptsConditionList(): void
    {
        $secret = new Secret(Secret::SENSITIVE, ['oauth-token', 'api-key']);

        self::assertTrue($secret->hasCondition('oauth-token'));
        self::assertTrue($secret->hasCondition('api-key'));
        self::assertSame(
            ['oauth-token', 'api-key'],
            \array_keys($secret->conditions),
        );
    }

    public function testConstructImmutableLocksImmediately(): void
    {
        $secret = new Secret(Secret::SENSITIVE, 'x', immutable: true);

        self::assertTrue($secret->isFrozen());
        self::assertTrue($secret->hasCondition('x'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret is immutable.');
        $secret->addCondition('y');
    }

    public function testTypeConstantsAndTypesList(): void
    {
        self::assertSame('sensitive', Secret::SENSITIVE);
        self::assertSame('credential', Secret::CREDENTIAL);
        self::assertSame([Secret::SENSITIVE, Secret::CREDENTIAL], Secret::TYPES);
    }

    #[DataProvider('provideValidTypesCaseInsensitive')]
    public function testConstructNormalizesTypeCase(
        string $input,
        string $expected,
    ): void {
        $secret = new Secret($input);

        self::assertSame($expected, $secret->type);
        self::assertSame($expected, (string) $secret);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideValidTypesCaseInsensitive(): \Generator
    {
        yield 'sensitive lower' => ['sensitive', Secret::SENSITIVE];
        yield 'sensitive upper' => ['SENSITIVE', Secret::SENSITIVE];
        yield 'sensitive mixed' => ['SeNsItIvE', Secret::SENSITIVE];
        yield 'credential lower' => ['credential', Secret::CREDENTIAL];
        yield 'credential upper' => ['CREDENTIAL', Secret::CREDENTIAL];
    }

    public function testConstructRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid secret type `bogus`.');

        // @phpstan-ignore-next-line Testing invalid input.
        new Secret('bogus');
    }

    // ── from() ───────────────────────────────────────────────────

    public function testFromNullReturnsNull(): void
    {
        self::assertNull(Secret::from(null));
    }

    public function testFromStringBuildsPolicy(): void
    {
        $secret = Secret::from(Secret::CREDENTIAL, 'db-dsn');

        self::assertNotNull($secret);
        self::assertSame(Secret::CREDENTIAL, $secret->type);
        self::assertTrue($secret->hasCondition('db-dsn'));
        self::assertFalse($secret->isFrozen());
    }

    public function testFromStringNormalizesTypeCase(): void
    {
        $secret = Secret::from('CREDENTIAL');

        self::assertNotNull($secret);
        self::assertSame(Secret::CREDENTIAL, $secret->type);
    }

    public function testFromStringRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore-next-line Testing invalid input.
        Secret::from('nope');
    }

    public function testFromInstanceMergesConditionsIntoNewInstance(): void
    {
        $base = new Secret(Secret::CREDENTIAL, ['db-dsn']);
        $merged = Secret::from($base, 'oauth-token');

        self::assertNotNull($merged);
        self::assertNotSame($base, $merged);
        self::assertSame(Secret::CREDENTIAL, $merged->type);
        self::assertTrue($merged->hasCondition('db-dsn'));
        self::assertTrue($merged->hasCondition('oauth-token'));
        self::assertFalse($base->hasCondition('oauth-token'));
    }

    public function testFromInstanceInheritsFreeze(): void
    {
        $base = new Secret(Secret::SENSITIVE, 'a', immutable: true);
        $copy = Secret::from($base, 'b');

        self::assertNotNull($copy);
        self::assertNotSame($base, $copy);
        self::assertTrue($base->isFrozen());
        self::assertTrue($copy->isFrozen());
        self::assertTrue($copy->hasCondition('a'));
        self::assertTrue($copy->hasCondition('b'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret is immutable.');
        $copy->addCondition('c');
    }

    public function testFromInstanceCanFreezeUnfrozenSource(): void
    {
        $base = new Secret(Secret::SENSITIVE, 'a');
        $copy = Secret::from($base, 'b', immutable: true);

        self::assertNotNull($copy);
        self::assertTrue($copy->isFrozen());
        self::assertTrue($copy->hasCondition('a'));
        self::assertTrue($copy->hasCondition('b'));
        self::assertFalse($base->isFrozen());
    }

    public function testFromInstanceExplicitFalseDoesNotClearSourceFreeze(): void
    {
        $base = new Secret(Secret::CREDENTIAL, immutable: true);
        $copy = Secret::from($base, immutable: false);

        self::assertNotNull($copy);
        self::assertTrue($copy->isFrozen());
    }

    public function testFromUnfrozenInstanceStaysUnfrozenByDefault(): void
    {
        $base = new Secret(Secret::SENSITIVE, 'a');
        $copy = Secret::from($base, 'b');

        self::assertNotNull($copy);
        self::assertFalse($copy->isFrozen());
        $copy->addCondition('c');
        self::assertTrue($copy->hasCondition('c'));
    }

    public function testFromMergesConditionArray(): void
    {
        $base = new Secret(Secret::SENSITIVE, 'a');
        $merged = Secret::from($base, ['b', 'c']);

        self::assertNotNull($merged);
        self::assertSame(['a', 'b', 'c'], \array_keys($merged->conditions));
    }

    // ── Conditions ───────────────────────────────────────────────

    public function testHasConditionIsCaseInsensitive(): void
    {
        $secret = new Secret(Secret::SENSITIVE, 'Db-DSN');

        self::assertTrue($secret->hasCondition('db-dsn'));
        self::assertTrue($secret->hasCondition('DB-DSN'));
        self::assertTrue($secret->hasCondition('Db-DSN'));
        self::assertFalse($secret->hasCondition('other'));
    }

    public function testAddConditionPreservesOriginalCasingInValue(): void
    {
        $secret = new Secret(Secret::SENSITIVE);
        $secret->addCondition('Db-DSN');

        self::assertSame(['db-dsn' => 'Db-DSN'], $secret->conditions);
    }

    public function testReaddingConditionMovesItToFront(): void
    {
        $secret = new Secret(Secret::SENSITIVE, ['a', 'b', 'c']);
        $secret->addCondition('B');

        self::assertSame(['b', 'a', 'c'], \array_keys($secret->conditions));
        self::assertSame('B', $secret->conditions['b']);
    }

    public function testRemoveConditionReturnsCountAndIsCaseInsensitive(): void
    {
        $secret = new Secret(Secret::SENSITIVE, ['a', 'b', 'c']);

        self::assertSame(2, $secret->removeCondition('A', 'missing', 'C'));
        self::assertSame(['b' => 'b'], $secret->conditions);
        self::assertSame(0, $secret->removeCondition('a'));
        self::assertSame(0, $secret->removeCondition());
    }

    #[DataProvider('provideInvalidConditions')]
    public function testAddConditionRejectsEmptyOrBracketWhitespace(
        string $condition,
    ): void {
        $secret = new Secret(Secret::SENSITIVE);

        $this->expectException(InvalidArgumentException::class);
        $secret->addCondition($condition);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideInvalidConditions(): \Generator
    {
        yield 'empty' => [''];
        yield 'leading space' => [' x'];
        yield 'trailing space' => ['x '];
        yield 'both' => [' x '];
        yield 'whitespace only' => ['   '];
        yield 'tab' => ["\tx"];
    }

    public function testConstructRejectsInvalidConditionInList(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Secret(Secret::SENSITIVE, ['ok', '']);
    }

    // ── setType ──────────────────────────────────────────────────

    public function testSetTypeChangesAndNormalizes(): void
    {
        $secret = new Secret(Secret::SENSITIVE);
        $secret->setType('CREDENTIAL');

        self::assertSame(Secret::CREDENTIAL, $secret->type);
    }

    public function testSetTypeRejectsInvalid(): void
    {
        $secret = new Secret(Secret::SENSITIVE);

        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line Testing invalid input.
        $secret->setType('nope');
    }

    // ── Freeze / mutability ──────────────────────────────────────

    public function testFreezeIsOneWay(): void
    {
        $secret = new Secret(Secret::SENSITIVE);
        self::assertFalse($secret->isFrozen());

        $secret->freeze();
        self::assertTrue($secret->isFrozen());

        $secret->freeze();
        self::assertTrue($secret->isFrozen());
    }

    public function testFrozenRejectsSetType(): void
    {
        $secret = ( new Secret(Secret::SENSITIVE) )->freeze();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret is immutable.');
        $secret->setType(Secret::CREDENTIAL);
    }

    public function testFrozenRejectsAddCondition(): void
    {
        $secret = ( new Secret(Secret::SENSITIVE) )->freeze();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret is immutable.');
        $secret->addCondition('x');
    }

    public function testFrozenRejectsRemoveCondition(): void
    {
        $secret = new Secret(Secret::SENSITIVE, 'x', immutable: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret is immutable.');
        $secret->removeCondition('x');
    }

    public function testUnfrozenMutableWhenNotPublic(): void
    {
        $this->resetAppEnv();
        new AppEnv(AppEnvironment::Development, public: false);
        self::assertFalse(AppEnv::isPublic());

        $secret = new Secret(Secret::SENSITIVE);
        $secret->addCondition('ok');
        $secret->setType(Secret::CREDENTIAL);

        self::assertSame(Secret::CREDENTIAL, $secret->type);
        self::assertTrue($secret->hasCondition('ok'));
    }

    public function testUnfrozenNotMutableInPublicEnvironment(): void
    {
        $this->resetAppEnv();
        new AppEnv(AppEnvironment::Production, public: true);
        self::assertTrue(AppEnv::isPublic());

        $secret = new Secret(Secret::SENSITIVE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret is not mutable in public environment.');
        $secret->addCondition('leak');
    }

    public function testPublicEnvironmentErrorPrecedesImmutableMessage(): void
    {
        $this->resetAppEnv();
        new AppEnv(AppEnvironment::Production, public: true);

        $secret = new Secret(Secret::SENSITIVE, immutable: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret is not mutable in public environment.');
        $secret->setType(Secret::CREDENTIAL);
    }

    public function testFrozenStillLockedWhenNotPublic(): void
    {
        $this->resetAppEnv();
        new AppEnv(AppEnvironment::Development, public: false);

        $secret = new Secret(Secret::SENSITIVE, immutable: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret is immutable.');
        $secret->addCondition('x');
    }

    // ── Serialize ────────────────────────────────────────────────

    public function testSerializeRoundTripPreservesPolicyNotPayloadSemantics(): void
    {
        $secret = new Secret(Secret::CREDENTIAL, ['Db-DSN', 'oauth']);
        $secret->freeze();

        $restored = \unserialize(\serialize($secret));

        self::assertInstanceOf(Secret::class, $restored);
        self::assertSame(Secret::CREDENTIAL, $restored->type);
        self::assertSame(['db-dsn' => 'Db-DSN', 'oauth' => 'oauth'], $restored->conditions);
        self::assertTrue($restored->isFrozen());
        self::assertSame(
            [
                'type'       => Secret::CREDENTIAL,
                'conditions' => ['db-dsn' => 'Db-DSN', 'oauth' => 'oauth'],
                'immutable'  => true,
            ],
            $secret->__serialize(),
        );
    }

    public function testUnserializeDefaultsTypeAndUnfrozen(): void
    {
        $secret = ( new \ReflectionClass(Secret::class) )->newInstanceWithoutConstructor();
        $secret->__unserialize([]);

        self::assertSame(Secret::SENSITIVE, $secret->type);
        self::assertSame([], $secret->conditions);
        self::assertFalse($secret->isFrozen());
    }

    public function testUnserializeRestoresFrozenLock(): void
    {
        $secret = ( new \ReflectionClass(Secret::class) )->newInstanceWithoutConstructor();
        $secret->__unserialize([
            'type'       => Secret::CREDENTIAL,
            'conditions' => ['a' => 'a'],
            'immutable'  => true,
        ]);

        self::assertTrue($secret->isFrozen());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secret is immutable.');
        $secret->addCondition('b');
    }

    public function testUnserializeNullImmutableBecomesUnfrozen(): void
    {
        $secret = ( new \ReflectionClass(Secret::class) )->newInstanceWithoutConstructor();
        $secret->__unserialize([
            'type'       => Secret::SENSITIVE,
            'conditions' => [],
            'immutable'  => null,
        ]);

        // `null ?? false` → unfrozen after restore (init window is not persisted).
        self::assertFalse($secret->isFrozen());
        $secret->addCondition('ok');
        self::assertTrue($secret->hasCondition('ok'));
    }

    // ── Invoke ───────────────────────────────────────────────────

    public function testInvokeRedactsWithPolicyType(): void
    {
        self::assertSame(
            '[sensitive::string]',
            ( new Secret(Secret::SENSITIVE) )('x'),
        );
        self::assertSame(
            '[credential::string]',
            ( new Secret(Secret::CREDENTIAL) )('token'),
        );
    }

    private function resetAppEnv(): void
    {
        $property = new \ReflectionProperty(AppEnv::class, 'instance');
        $property->setValue(null, null);
    }
}

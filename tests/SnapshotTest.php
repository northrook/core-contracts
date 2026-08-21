<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Assets\AssetType;
use Northrook\Container\Secret;
use Northrook\Contracts\Tests\Support\MixedArray;
use Northrook\Contracts\Tests\Support\SecretMask;
use Northrook\Parameter;
use Northrook\Parameter\Secret as SecretPolicy;
use Northrook\Parameter\Type as ParameterType;
use Northrook\ParameterDefinition;
use Northrook\Snapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SnapshotTest extends TestCase
{
    /**
     * @param resource $value
     */
    #[DataProvider('provideScalarTypes')]
    public function testFromReportsPhpType(
        mixed  $value,
        string $expectedType,
    ): void {
        $snapshot = Snapshot::from($value);

        self::assertSame($expectedType, $snapshot->type);
        self::assertSame($value, $snapshot->value);
    }

    /**
     * @return \Generator<string, array{0: mixed, 1: string}>
     */
    public static function provideScalarTypes(): \Generator
    {
        yield 'string' => ['abc', 'string'];
        yield 'integer' => [42, 'integer'];
        yield 'double' => [1.5, 'double'];
        yield 'boolean' => [true, 'boolean'];
        yield 'null' => [null, 'NULL'];
        yield 'array' => [[1], 'array'];
    }
    public function testFromPreservesScalarsAndDetachesArrays(): void
    {
        $source = ['a' => 1, 'b' => ['c' => true]];
        $snap   = Snapshot::from($source);

        self::assertSame('array', $snap->type);
        self::assertSame($source, $snap->value);

        $source['b']['c'] = false;
        self::assertTrue($snap->value['b']['c']);
    }

    public function testArrayCycleBroken(): void
    {
        $a         = ['label' => 'a'];
        $b         = ['label' => 'b', 'peer' => &$a];
        $a['peer'] = &$b;

        $value = MixedArray::from(Snapshot::value($a));
        $peer  = MixedArray::at($value, 'peer');

        self::assertSame('a', $value['label']);
        self::assertSame('b', $peer['label']);
        self::assertSame('[Recursion]', $peer['peer']);
    }

    public function testDepthBudgetTruncatesNesting(): void
    {
        $nested = ['l1' => ['l2' => ['l3' => ['l4' => 'deep']]]];

        $value = Snapshot::value($nested, maxDepth: 1, maxNodes: 1_000);

        self::assertSame(
            ['l1' => ['l2' => '[Snapshot: max depth]']],
            $value,
        );
    }

    public function testNodeBudgetTruncatesWideArrays(): void
    {
        $wide = \range(1, 20);

        $value = Snapshot::value($wide, maxDepth: 8, maxNodes: 5);

        self::assertIsArray($value);
        self::assertContains('[Snapshot: budget exhausted]', $value);
        self::assertLessThan(20, \count($value));
    }

    public function testContextSharesOneBudget(): void
    {
        $context = [
            'a' => \range(1, 10),
            'b' => \range(1, 10),
        ];

        $frozen = Snapshot::context($context, maxDepth: 4, maxNodes: 8);

        $flat = [];
        \array_walk_recursive($frozen, static function(
            mixed $v,
        ) use (&$flat): void {
            $flat[] = $v;
        });

        self::assertContains('[Snapshot: budget exhausted]', $flat);
    }

    public function testThrowableIsSummarized(): void
    {
        $value = MixedArray::from(Snapshot::value(new \RuntimeException('boom', 7)));

        self::assertSame(\RuntimeException::class, $value['class']);
        self::assertSame('boom', $value['message']);
        self::assertSame(7, $value['code']);
    }

    public function testEnumBecomesCaseName(): void
    {
        $snapshot = Snapshot::from(AssetType::Script);

        self::assertSame('object', $snapshot->type);
        self::assertSame('Script', $snapshot->value);
    }

    public function testClosureIsDescribed(): void
    {
        $value = Snapshot::value(static fn(): int => 1);

        self::assertIsString($value);
        self::assertStringStartsWith('{closure:', $value);
        self::assertStringContainsString(self::class . '::testClosureIsDescribed()', $value);
        self::assertStringNotContainsString('@', $value);
    }

    public function testFirstClassCallableClosureIsDescribedByName(): void
    {
        $value = Snapshot::value(strlen(...));

        self::assertSame('strlen', $value);
    }

    public function testResourceIsDescribed(): void
    {
        $handle = \fopen('php://memory', 'rb');

        if ($handle === false) {
            self::fail('Failed to open php://memory.');
        }

        self::assertSame('[resource: stream]', Snapshot::value($handle));
        self::assertSame('resource', Snapshot::from($handle)->type);

        \fclose($handle);

        self::assertSame('[resource: closed]', Snapshot::value($handle));
    }

    public function testDateTimeIsCopiedNotShared(): void
    {
        $original = new \DateTimeImmutable('2020-01-01 12:00:00');

        $value = Snapshot::value($original);

        self::assertInstanceOf(\DateTimeImmutable::class, $value);
        self::assertNotSame($original, $value);
        self::assertSame('2020-01-01 12:00:00', $value->format('Y-m-d H:i:s'));
    }

    public function testObjectCycleUsesRecursionMarker(): void
    {
        $parent        = new \stdClass;
        $child         = new \stdClass;
        $parent->child = $child;
        $child->parent = $parent;

        $value      = MixedArray::from(Snapshot::value($parent));
        $properties = MixedArray::at($value, 'properties');
        $child      = MixedArray::at($properties, 'child');
        $childProps = MixedArray::at($child, 'properties');

        self::assertSame(\stdClass::class, $value['class']);
        self::assertSame(\stdClass::class, $child['class']);
        self::assertSame(
            '[Recursion: ' . \stdClass::class . ']',
            $childProps['parent'],
        );
    }

    public function testObjectIsDescribedReflectively(): void
    {
        $original = new SnapshotCloneOnlyFixture('payload');

        $value      = MixedArray::from(Snapshot::value($original));
        $properties = MixedArray::at($value, 'properties');

        self::assertSame(SnapshotCloneOnlyFixture::class, $value['class']);
        self::assertSame('payload', $properties['label']);
        self::assertIsString($properties['fn']);
        self::assertStringStartsWith('{closure:', $properties['fn']);
    }

    public function testObjectWithThrowingSerializeIsStillDescribed(): void
    {
        $value = Snapshot::value(new SnapshotUncopyableFixture);

        self::assertSame(
            [
                'class'      => SnapshotUncopyableFixture::class,
                'properties' => [],
            ],
            $value,
        );
    }

    public function testSecretAttributeOnPropertyIsRedacted(): void
    {
        $value = Snapshot::value(new SnapshotSecretPropertyFixture('top-secret', 'visible'));

        self::assertSame(
            [
                'class'      => SnapshotSecretPropertyFixture::class,
                'properties' => [
                    'token' => SecretMask::sensitive('top-secret'),
                    'label' => 'visible',
                ],
            ],
            $value,
        );
    }

    public function testSecretInContextIsRedacted(): void
    {
        $frozen = Snapshot::context([
            'password' => new Parameter(
                key   : 'auth.password',
                value : 'hunter2',
                type  : ParameterType::Setting,
                secret: SecretPolicy::SENSITIVE,
                tags  : [],
            ),
            'user'     => new SnapshotSecretPropertyFixture('abc', 'ada'),
        ]);

        $passwordProps = MixedArray::at(MixedArray::at($frozen, 'password'), 'properties');
        $userProps     = MixedArray::at(MixedArray::at($frozen, 'user'), 'properties');

        self::assertSame(SecretMask::sensitive('hunter2'), $passwordProps['value']);
        self::assertSame(SecretMask::sensitive('abc'), $userProps['token']);
        self::assertSame('ada', $userProps['label']);
    }

    public function testParameterPayloadIsRedacted(): void
    {
        $sensitive = new Parameter(
            key   : 'app.token',
            value : 'secret123',
            type  : ParameterType::Setting,
            secret: SecretPolicy::SENSITIVE,
            tags  : ['api' => 'api'],
        );
        $credential = new Parameter(
            key   : 'db.dsn',
            value : 'postgres://…',
            type  : ParameterType::Setting,
            secret: SecretPolicy::CREDENTIAL,
            tags  : [],
        );

        $sensitiveSnap   = MixedArray::from(Snapshot::value($sensitive));
        $sensitiveProps  = MixedArray::at($sensitiveSnap, 'properties');
        $credentialSnap  = MixedArray::from(Snapshot::value($credential));
        $credentialProps = MixedArray::at($credentialSnap, 'properties');

        self::assertSame(Parameter::class, $sensitiveSnap['class']);
        self::assertSame('app.token', $sensitiveProps['key']);
        self::assertSame(SecretMask::sensitive('secret123'), $sensitiveProps['value']);
        self::assertSame('Setting', $sensitiveProps['type']);
        self::assertSame(['api' => 'api'], $sensitiveProps['tags']);

        self::assertSame(Parameter::class, $credentialSnap['class']);
        self::assertSame('db.dsn', $credentialProps['key']);
        self::assertSame('[secret::credential]', $credentialProps['value']);
    }

    public function testParameterReferencePayloadIsRedacted(): void
    {
        $reference = new ParameterDefinition(
            'app.token',
            'secret123',
            SecretPolicy::SENSITIVE,
        );

        $snap  = MixedArray::from(Snapshot::value($reference));
        $props = MixedArray::at($snap, 'properties');

        self::assertSame(ParameterDefinition::class, $snap['class']);
        self::assertSame('app.token', $props['key']);
        self::assertSame(SecretMask::sensitive('secret123'), $props['value']);
    }

    public function testParameterInContextKeepsEnvelope(): void
    {
        $parameter = new Parameter(
            key   : 'db.dsn',
            value : 'postgres://…',
            type  : ParameterType::Setting,
            secret: SecretPolicy::CREDENTIAL,
            tags  : [],
        );

        $frozen = Snapshot::context(['db' => $parameter]);
        $props  = MixedArray::at(MixedArray::at($frozen, 'db'), 'properties');

        self::assertSame(Parameter::class, MixedArray::at($frozen, 'db')['class']);
        self::assertSame('db.dsn', $props['key']);
        self::assertSame('[secret::credential]', $props['value']);
    }

    public function testCommitOnDestructIsNotInvokedDuringSnapshot(): void
    {
        SnapshotDestructFixture::$destructCount = 0;

        $service = new SnapshotDestructFixture;
        Snapshot::value($service);

        self::assertSame(0, SnapshotDestructFixture::$destructCount);

        unset($service);

        self::assertSame(1, SnapshotDestructFixture::$destructCount);
    }

    public function testDistinctEqualArraysNotMarkedRecursive(): void
    {
        $value = Snapshot::value(['a' => [1, 2], 'b' => [1, 2]]);

        self::assertSame(['a' => [1, 2], 'b' => [1, 2]], $value);
    }

    public function testContextNullAndEmptyReturnEmptyArray(): void
    {
        self::assertSame([], Snapshot::context(null));
        self::assertSame([], Snapshot::context([]));
    }

    public function testFreezeReturnsFrozenValue(): void
    {
        self::assertSame(['x' => 1], Snapshot::freeze(['x' => 1]));
        self::assertSame('raw', Snapshot::freeze('raw'));
    }

    public function testParseSnapshotsEachValue(): void
    {
        $snapshots = Snapshot::parse([1, 'a', null]);

        self::assertCount(3, $snapshots);
        self::assertContainsOnlyInstancesOf(Snapshot::class, $snapshots);
        self::assertSame('integer', $snapshots[0]->type);
        self::assertSame('string', $snapshots[1]->type);
        self::assertSame('NULL', $snapshots[2]->type);
    }

    public function testJsonSerializeAndToString(): void
    {
        $stringSnapshot = Snapshot::from('hi');
        self::assertSame('hi', (string) $stringSnapshot);

        $intSnapshot = Snapshot::from(123);

        self::assertSame(
            ['type' => 'integer', 'value' => 123],
            $intSnapshot->jsonSerialize(),
        );
        self::assertSame('{"type":"integer","value":123}', (string) $intSnapshot);
    }
}

/**
 * Closure property — previously forced serialize failure / clone fallback.
 */
final class SnapshotCloneOnlyFixture
{
    public \Closure $fn;

    public function __construct(
        public string $label,
    ) {
        $this->fn = fn(): string => $this->label;
    }
}

/**
 * Throwing `__serialize` must not block reflective description.
 */
final class SnapshotUncopyableFixture
{
    public function __serialize(): array
    {
        throw new \LogicException('Cannot serialize.');
    }

    private function __clone() {}
}

final class SnapshotSecretPropertyFixture
{
    public function __construct(
        #[Secret]
        public string $token,
        public string $label,
    ) {}
}

/**
 * Simulates a persistence layer that commits on destruction.
 */
final class SnapshotDestructFixture
{
    public static int $destructCount = 0;

    public function __destruct()
    {
        self::$destructCount++;
    }
}

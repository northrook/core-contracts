<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Snapshot;
use PHPUnit\Framework\TestCase;

final class SnapshotTest extends TestCase
{
    public function testFreezeBreaksSelfReferentialArrayCycle(): void
    {
        $array         = ['label' => 'root'];
        $array['self'] = &$array;

        $frozen = Snapshot::freeze($array);

        if (! \is_array($frozen)) {
            self::fail('Expected frozen array.');
        }
        self::assertSame('root', $frozen['label']);
        self::assertSame('[Recursion]', $frozen['self']);
        self::assertSame('root', $array['label']);
        self::assertSame($array, $array['self']);
    }

    public function testFreezeBreaksMutualArrayReferenceCycle(): void
    {
        $alpha         = ['label' => 'alpha'];
        $beta          = ['label' => 'beta'];
        $alpha['peer'] = &$beta;
        $beta['peer']  = &$alpha;

        $frozen = Snapshot::freeze(['graph' => &$alpha]);

        if (! \is_array($frozen) || ! \is_array($frozen['graph'])) {
            self::fail('Expected frozen graph array.');
        }
        $graph = $frozen['graph'];
        self::assertSame('alpha', $graph['label']);
        if (! \is_array($graph['peer'])) {
            self::fail('Expected peer array.');
        }
        self::assertSame('beta', $graph['peer']['label']);
        self::assertSame('[Recursion]', $graph['peer']['peer']);
        self::assertSame('alpha', $alpha['label']);
        self::assertSame('beta', $beta['label']);
        self::assertSame($beta, $alpha['peer']);
        self::assertSame($alpha, $beta['peer']);
    }

    public function testFreezeDoesNotFalsePositiveOnEqualDistinctArrays(): void
    {
        $leaf    = ['a' => 1];
        $payload = [
            'one'    => ['a' => 1],
            'two'    => ['a' => 1],
            'nested' => [
                'inner' => $leaf,
            ],
        ];

        $frozen = Snapshot::freeze($payload);

        if (! \is_array($frozen)) {
            self::fail('Expected frozen array.');
        }
        self::assertSame(['a' => 1], $frozen['one']);
        self::assertSame(['a' => 1], $frozen['two']);
        if (! \is_array($frozen['nested'])) {
            self::fail('Expected nested array.');
        }
        self::assertSame(['a' => 1], $frozen['nested']['inner']);
        self::assertSame($frozen['one'], $frozen['two']);
    }

    public function testFreezePreservesObjectCyclesViaWeakMap(): void
    {
        $root                = new \stdClass();
        $root->id            = 'root';
        $root->child         = new \stdClass();
        $root->child->id     = 'child';
        $root->child->parent = $root;

        $frozen = Snapshot::freeze($root);

        self::assertInstanceOf(\stdClass::class, $frozen);
        self::assertSame('root', $frozen->id);
        self::assertSame('child', $frozen->child->id);
        self::assertSame($frozen, $frozen->child->parent);
    }

    public function testContextFreezesNestedArrayCycles(): void
    {
        $cycle         = ['n' => 1];
        $cycle['self'] = &$cycle;

        $context = Snapshot::context([
            'id'    => 'req-1',
            'cycle' => $cycle,
        ]);

        self::assertSame('req-1', $context['id']);
        if (! \is_array($context['cycle'])) {
            self::fail('Expected cycle array in context.');
        }
        self::assertSame(1, $context['cycle']['n']);
        self::assertSame('[Recursion]', $context['cycle']['self']);
    }

    public function testFreezeFallsBackToCloneWhenSerializeFails(): void
    {
        $original = new SnapshotUnserializableCloneable();

        $frozen = Snapshot::freeze($original);

        self::assertInstanceOf(SnapshotUnserializableCloneable::class, $frozen);
        self::assertNotSame($original, $frozen);
        self::assertSame('ok', $frozen->label);
    }

    public function testFreezeReturnsUncloneableMarkerWhenCopyImpossible(): void
    {
        $frozen = Snapshot::freeze(new SnapshotUncopyable());

        self::assertSame('[Uncloneable: ' . SnapshotUncopyable::class . ']', $frozen);
    }

    public function testContextSurvivesUnserializableValues(): void
    {
        $context = Snapshot::context([
            'id'         => 'req-1',
            'cloneable'  => new SnapshotUnserializableCloneable(),
            'uncopyable' => new SnapshotUncopyable(),
        ]);

        self::assertSame('req-1', $context['id']);
        self::assertInstanceOf(SnapshotUnserializableCloneable::class, $context['cloneable']);
        self::assertSame(
            '[Uncloneable: ' . SnapshotUncopyable::class . ']',
            $context['uncopyable'],
        );
    }
}

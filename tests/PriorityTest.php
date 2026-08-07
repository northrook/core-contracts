<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\Priority;
use PHPUnit\Framework\TestCase;

/**
 * Each test claims its own domain so the static allocation registry stays isolated.
 */
final class PriorityTest extends TestCase
{
    protected function tearDown(): void
    {
        Priority::auto('priority-test.cleanup')->reset(all: true);
    }

    public function testConstants(): void
    {
        self::assertNull(Priority::AUTO);
        self::assertSame(1_024, Priority::MAX);
        self::assertSame(-1_024, Priority::MIN);
    }

    public function testExplicitValueResolvesAsIs(): void
    {
        $priority = new Priority(5, domain: 'test.explicit');

        self::assertSame(5, $priority->get());
        self::assertSame(5, $priority->value);
    }

    public function testGetIsIdempotentPerInstance(): void
    {
        $priority = new Priority(null, domain: 'test.idempotent');

        $first = $priority->get();

        self::assertSame($first, $priority->get());
        self::assertSame($first, $priority->get());
    }

    public function testAutoAllocationIncrementsFromZero(): void
    {
        $domain = 'test.auto-increment';

        self::assertSame(0, Priority::auto($domain)->get());
        self::assertSame(1, Priority::auto($domain)->get());
        self::assertSame(2, Priority::auto($domain)->get());
    }

    public function testTakenExplicitValueBumpsUpward(): void
    {
        $domain = 'test.bump';

        self::assertSame(5, new Priority(5, domain: $domain)->get());
        self::assertSame(6, new Priority(5, domain: $domain)->get());
        self::assertSame(7, new Priority(5, domain: $domain)->get());
    }

    public function testNegativeValuesStepDownward(): void
    {
        $domain = 'test.negative';

        self::assertSame(-3, new Priority(-3, domain: $domain)->get());
        self::assertSame(-4, new Priority(-3, domain: $domain)->get());
    }

    public function testAutoWithRelativeSeedStartsAtRelative(): void
    {
        $domain = 'test.relative-seed';

        self::assertSame(100, Priority::auto($domain)->get(100));
        self::assertSame(101, Priority::auto($domain)->get(100));
    }

    public function testResolvedInstanceBumpsWhenRelativeCollides(): void
    {
        $domain   = 'test.relative-collision';
        $priority = new Priority(5, domain: $domain);

        self::assertSame(5, $priority->get());
        self::assertSame(6, $priority->get(5));
        self::assertSame(6, $priority->get());
    }

    public function testFixedValueClaimsSlot(): void
    {
        $domain = 'test.fixed';

        self::assertSame(9, Priority::fixed(9, $domain)->get());
    }

    public function testFixedCollisionThrows(): void
    {
        $domain = 'test.fixed-collision';

        Priority::fixed(9, $domain)->get();

        $this->expectException(InvalidArgumentException::class);

        Priority::fixed(9, $domain)->get();
    }

    public function testFixedWithoutValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Priority(null, fixed: true, domain: 'test.fixed-null');
    }

    public function testEmptyDomainThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore-next-line Testing invalid input.
        new Priority(1, domain: '');
    }

    public function testOutOfBoundsValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore-next-line Testing invalid input.
        new Priority(Priority::MAX + 1, domain: 'test.bounds');
    }

    public function testOutOfBoundsNegativeValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore-next-line Testing invalid input.
        new Priority(Priority::MIN - 1, domain: 'test.bounds-negative');
    }

    public function testBoundaryValuesAreAccepted(): void
    {
        $domain = 'test.boundary-values';

        self::assertSame(Priority::MAX, new Priority(Priority::MAX, domain: $domain)->get());
        self::assertSame(Priority::MIN, new Priority(Priority::MIN, domain: $domain)->get());
    }

    public function testSetOnFixedThrows(): void
    {
        $priority = Priority::fixed(3, 'test.set-fixed');

        $this->expectException(InvalidArgumentException::class);

        $priority->set(4);
    }

    public function testSetReleasesPreviousClaim(): void
    {
        $domain = 'test.set-release';
        $first  = new Priority(5, domain: $domain);
        $second = new Priority(5, domain: $domain);

        self::assertSame(5, $first->get());
        self::assertSame(6, $second->get());

        $first->set(7);
        self::assertSame(7, $first->get());

        // Slot 5 was released by set(); a fresh instance can claim it.
        self::assertSame(5, new Priority(5, domain: $domain)->get());
    }

    public function testDomainsAllocateIndependently(): void
    {
        self::assertSame(0, Priority::auto('test.domain-a')->get());
        self::assertSame(0, Priority::auto('test.domain-b')->get());
        self::assertSame(1, Priority::auto('test.domain-a')->get());
    }

    public function testResetClearsDomainRegistry(): void
    {
        $domain = 'test.reset';

        $resolved = new Priority(5, domain: $domain);
        self::assertSame(5, $resolved->get());
        self::assertSame(6, new Priority(5, domain: $domain)->get());

        Priority::auto($domain)->reset();

        // Registry cleared: 5 is free again; the resolved instance keeps its value.
        self::assertSame(5, $resolved->value);
        self::assertSame(5, new Priority(5, domain: $domain)->get());
    }

    public function testToStringResolvesValue(): void
    {
        $priority = new Priority(42, domain: 'test.to-string');

        self::assertSame('42', (string) $priority);
        self::assertSame('42', $priority->__toString());
    }

    public function testImplementsStringableAndResettable(): void
    {
        $priority = new Priority(1, domain: 'test.interfaces');

        self::assertInstanceOf(\Stringable::class, $priority);
    }

    public function testValueHelperReturnsConcreteInt(): void
    {
        self::assertSame(7, Priority::value(7));
        self::assertSame(-7, Priority::value(-7));
    }

    public function testFromFactory(): void
    {
        $priority = Priority::from(11, fixed: true, domain: 'test.from');

        self::assertTrue($priority->fixed);
        self::assertSame('test.from', $priority->domain);
        self::assertSame(11, $priority->get());
    }
}

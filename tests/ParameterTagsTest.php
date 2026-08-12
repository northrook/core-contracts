<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\Parameter\Tags;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParameterTagsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function testDefaultConstructorIsEmpty(): void
    {
        $tags = new Tags();

        self::assertCount(0, $tags);
        self::assertSame([], $tags->value);
        self::assertSame([], \iterator_to_array($tags));
    }

    public function testConstructorAcceptsSingleString(): void
    {
        $tags = new Tags('Hello');

        self::assertSame(['hello' => 'Hello'], $tags->value);
        self::assertCount(1, $tags);
    }

    public function testConstructorAcceptsList(): void
    {
        $tags = new Tags(['A', 'b', 'A']);

        self::assertSame(['a' => 'A', 'b' => 'b'], $tags->value);
    }

    public function testConstructorEmptyArraySkipsAdd(): void
    {
        $tags = new Tags([]);

        self::assertCount(0, $tags);
    }

    public function testConstructorRejectsInvalidTag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter Tag must be a non-empty string, with no bracketing whitespace.');
        new Tags(['ok', '']);
    }

    // -------------------------------------------------------------------------
    // add / casing / preservation
    // -------------------------------------------------------------------------

    public function testAddIsFluentAndPreservesFirstCasing(): void
    {
        $tags = new Tags();
        $same = $tags->add('Foo', 'bar', 'FOO', 'Bar', 'foo');

        self::assertSame($tags, $same);
        self::assertSame(['foo' => 'Foo', 'bar' => 'bar'], $tags->value);
    }

    public function testAddVarargsAppends(): void
    {
        $tags = new Tags('one');
        $tags->add('two', 'three');

        self::assertSame(['one' => 'one', 'two' => 'two', 'three' => 'three'], $tags->value);
    }

    public function testAddWithNoArgumentsIsNoop(): void
    {
        $tags = new Tags('keep');
        $tags->add();

        self::assertSame(['keep' => 'keep'], $tags->value);
    }

    public function testAddAcceptsInternalWhitespaceAndPunctuation(): void
    {
        $tags = new Tags();
        $tags->add('a b', 'a-b_c.1', 'x/y');

        self::assertTrue($tags->has('a b', 'a-b_c.1', 'x/y'));
        self::assertSame('a b', $tags->value['a b']);
    }

    #[DataProvider('provideInvalidTags')]
    public function testAddRejectsEmptyOrBracketWhitespace(
        string $tag,
    ): void {
        $tags = new Tags();

        try {
            $tags->add($tag);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(
                'Parameter Tag must be a non-empty string, with no bracketing whitespace.',
                $exception->getMessage(),
            );
            self::assertSame($tag, $exception->context['tag']);
            self::assertSame([$tag], $exception->context['tags']);
        }

        self::assertCount(0, $tags);
    }

    public static function provideInvalidTags(): \Generator
    {
        yield 'empty' => [''];
        yield 'space' => [' '];
        yield 'spaces' => ['   '];
        yield 'leading space' => ['  a'];
        yield 'trailing space' => ['a  '];
        yield 'both sides' => [' a '];
        yield 'leading tab' => ["\ta"];
        yield 'trailing tab' => ["a\t"];
        yield 'trailing newline' => ["a\n"];
        yield 'leading newline' => ["\na"];
        yield 'carriage return' => ["a\r"];
    }

    public function testAddRejectsLaterInvalidWithoutKeepingPriorInSameCall(): void
    {
        $tags = new Tags();

        try {
            $tags->add('ok', ' bad');
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $exception) {
            self::assertSame(' bad', $exception->context['tag']);
            self::assertSame(['ok', ' bad'], $exception->context['tags']);
        }

        // First tag was applied before the invalid sibling failed.
        self::assertSame(['ok' => 'ok'], $tags->value);
    }

    public function testByteStrtolowerDoesNotFoldMultibyteCase(): void
    {
        // Keys use \strtolower, not mb_strtolower — "Ä" does not become "ä".
        $tags = new Tags('Ä');

        self::assertTrue($tags->has('Ä'));
        self::assertFalse($tags->has('ä'));
        self::assertArrayHasKey('Ä', $tags->value);
        self::assertArrayNotHasKey('ä', $tags->value);
    }

    // -------------------------------------------------------------------------
    // has
    // -------------------------------------------------------------------------

    public function testHasIsCaseInsensitiveAndRequiresAll(): void
    {
        $tags = new Tags(['Alpha', 'Beta']);

        self::assertTrue($tags->has('alpha'));
        self::assertTrue($tags->has('ALPHA', 'beta'));
        self::assertFalse($tags->has('alpha', 'missing'));
        self::assertFalse($tags->has('missing'));
    }

    public function testHasWithNoArgumentsIsVacuousTrue(): void
    {
        // array_all([]) === true
        self::assertTrue(( new Tags() )->has());
        self::assertTrue(( new Tags('x') )->has());
    }

    public function testHasDoesNotTrimLookup(): void
    {
        $tags = new Tags('tag');

        self::assertFalse($tags->has(' tag'));
        self::assertFalse($tags->has('tag '));
    }

    // -------------------------------------------------------------------------
    // remove
    // -------------------------------------------------------------------------

    public function testRemoveIsCaseInsensitiveAndReturnsCount(): void
    {
        $tags = new Tags(['Alpha', 'Beta', 'Gamma']);

        self::assertSame(1, $tags->remove('ALPHA'));
        self::assertSame(['beta' => 'Beta', 'gamma' => 'Gamma'], $tags->value);

        // Duplicate lookup after removal counts once; missing ignored.
        self::assertSame(1, $tags->remove('beta', 'BETA', 'missing'));
        self::assertSame(['gamma' => 'Gamma'], $tags->value);
    }

    public function testRemoveMissingAndEmptyVarargs(): void
    {
        $tags = new Tags('only');

        self::assertSame(0, $tags->remove('nope'));
        self::assertSame(0, $tags->remove());
        self::assertSame(['only' => 'only'], $tags->value);
    }

    // -------------------------------------------------------------------------
    // set / clear
    // -------------------------------------------------------------------------

    public function testSetReplacesEntireCollection(): void
    {
        $tags = new Tags(['old', 'keep-me-not']);
        $same = $tags->set('New', 'NEW', 'other');

        self::assertSame($tags, $same);
        self::assertSame(['new' => 'New', 'other' => 'other'], $tags->value);
    }

    public function testSetWithNoArgumentsClears(): void
    {
        $tags = new Tags(['a', 'b']);
        $tags->set();

        self::assertCount(0, $tags);
        self::assertSame([], $tags->value);
    }

    public function testClearIsFluent(): void
    {
        $tags = new Tags(['a', 'b']);
        $same = $tags->clear();

        self::assertSame($tags, $same);
        self::assertCount(0, $tags);
        self::assertFalse($tags->has('a'));
    }

    public function testSetRejectsInvalidAfterClear(): void
    {
        $tags = new Tags('prior');

        try {
            $tags->set('ok', '');
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            // set() clears before add(); failed add leaves the cleared state + prior ok tag.
        }

        self::assertSame(['ok' => 'ok'], $tags->value);
    }

    // -------------------------------------------------------------------------
    // Iteration / Countable / value hook
    // -------------------------------------------------------------------------

    public function testImplementsCountableAndIteratorAggregate(): void
    {
        $tags = new Tags(['MiXeD', 'second']);

        self::assertInstanceOf(\Countable::class, $tags);
        self::assertInstanceOf(\IteratorAggregate::class, $tags);
        self::assertCount(2, $tags);
        self::assertSame(
            ['mixed' => 'MiXeD', 'second' => 'second'],
            \iterator_to_array($tags),
        );
    }

    public function testValueHookReflectsMutations(): void
    {
        $tags = new Tags();
        self::assertSame([], $tags->value);

        $tags->add('X');
        self::assertSame(['x' => 'X'], $tags->value);

        $tags->remove('x');
        self::assertSame([], $tags->value);
    }

    public function testIterationOrderFollowsInsertion(): void
    {
        $tags = new Tags();
        $tags->add('c', 'a', 'b');

        self::assertSame(['c', 'a', 'b'], \array_keys($tags->value));
        self::assertSame(['c', 'a', 'b'], \array_keys(\iterator_to_array($tags)));
    }
}

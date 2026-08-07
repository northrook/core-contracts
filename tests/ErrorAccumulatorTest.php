<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ErrorAccumulator;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\RuntimeException;
use Northrook\Contracts\Tests\Support\MixedArray;
use PHPUnit\Framework\TestCase;

final class ErrorAccumulatorTest extends TestCase
{
    protected function setUp(): void
    {
        ErrorAccumulator::clear('*');
    }

    protected function tearDown(): void
    {
        ErrorAccumulator::clear('*');
    }

    public function testConstructorRegistersInstance(): void
    {
        $accumulator = new ErrorAccumulator('registry');

        self::assertSame('registry', $accumulator->reference);
        self::assertSame($accumulator, ErrorAccumulator::get('registry'));
    }

    public function testReferenceKeyIsTrimmedAndLowercased(): void
    {
        // @phpstan-ignore-next-line Testing reference normalization.
        $accumulator = new ErrorAccumulator('  MixedCase ');

        self::assertSame('mixedcase', $accumulator->reference);
        self::assertSame($accumulator, ErrorAccumulator::get('MIXEDCASE'));
    }

    public function testDuplicateReferenceThrows(): void
    {
        new ErrorAccumulator('taken');

        try {
            new ErrorAccumulator('taken');
            self::fail('Expected InvalidArgumentException for duplicate reference');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString("reference 'taken' is already registered", $exception->getMessage());
            self::assertSame('reference', $exception->context['name']);
            self::assertSame('unused accumulator key', $exception->context['expected']);
            self::assertSame('taken', $exception->context['received']);
        }
    }

    public function testEmptyReferenceThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a non-empty reference key string');

        new ErrorAccumulator('   ');
    }

    public function testAddWithoutNoteAppendsToList(): void
    {
        $accumulator = new ErrorAccumulator('plain');
        $first       = new \RuntimeException('one');
        $second      = new \RuntimeException('two');

        $result = $accumulator->add($first)->add($second);

        self::assertSame($accumulator, $result);
        self::assertSame(2, $accumulator->count());
        self::assertSame(2, \count($accumulator));
        self::assertTrue($accumulator->hasErrors());
        self::assertSame([$first, $second], \array_values($accumulator->getErrors()));
    }

    public function testAddWithNoteIndexesByCountAndNote(): void
    {
        $accumulator = new ErrorAccumulator('noted');

        $accumulator->add(new \RuntimeException('first'), 'boot');
        $accumulator->add(new \RuntimeException('second'), 'runtime');

        $errors = $accumulator->getErrors();

        self::assertSame(['0 boot', '1 runtime'], \array_keys($errors));
    }

    public function testHasErrorsIsFalseWhenEmpty(): void
    {
        $accumulator = new ErrorAccumulator('pristine');

        self::assertFalse($accumulator->hasErrors());
        self::assertSame(0, $accumulator->count());
        self::assertSame([], $accumulator->getErrors());
    }

    public function testToStringImplodesErrorMessages(): void
    {
        $accumulator = new ErrorAccumulator('stringy');
        $first       = new \RuntimeException('alpha');
        $second      = new \RuntimeException('beta');

        $accumulator->add($first);
        $accumulator->add($second);

        self::assertSame((string) $first . "\n" . (string) $second, (string) $accumulator);
    }

    public function testRegisterCreatesAndReusesAccumulator(): void
    {
        $first  = ErrorAccumulator::register('shared-key', new \RuntimeException('one'));
        $second = ErrorAccumulator::register('shared-key', new \RuntimeException('two'), 'again');

        self::assertSame($first, $second);
        self::assertSame(2, $first->count());
    }

    public function testGetReturnsNullForUnknownReference(): void
    {
        self::assertNull(ErrorAccumulator::get('missing'));
    }

    public function testCheckReturnsFalseWhenMissingOrEmpty(): void
    {
        self::assertFalse(ErrorAccumulator::check('absent'));

        new ErrorAccumulator('hollow');

        self::assertFalse(ErrorAccumulator::check('hollow'));
    }

    public function testCheckThrowsWithDefaultMessageAndContext(): void
    {
        ErrorAccumulator::register('pipeline', new \RuntimeException('first failure'));
        ErrorAccumulator::register('pipeline', new \RuntimeException('second failure'));

        try {
            ErrorAccumulator::check('pipeline');
            self::fail('Expected RuntimeException from check()');
        } catch (RuntimeException $exception) {
            self::assertStringStartsWith(
                'ErrorAccumulator: pipeline encountered 2 errors:',
                $exception->getMessage(),
            );
            self::assertStringContainsString('first failure', $exception->getMessage());
            self::assertStringContainsString('second failure', $exception->getMessage());
            self::assertSame('pipeline', $exception->context['reference']);
            self::assertCount(2, MixedArray::at($exception->context, 'errors'));
        }
    }

    public function testCheckUsesConstructorMessageAsHeader(): void
    {
        $accumulator = new ErrorAccumulator('headed', 'Custom header');
        $accumulator->add(new \RuntimeException('body'));

        try {
            ErrorAccumulator::check('headed');
            self::fail('Expected RuntimeException from check()');
        } catch (RuntimeException $exception) {
            self::assertStringStartsWith('Custom header', $exception->getMessage());
            self::assertStringContainsString('body', $exception->getMessage());
        }
    }

    public function testCheckUsesProvidedMessageOverride(): void
    {
        ErrorAccumulator::register('override', new \RuntimeException('details'));

        try {
            ErrorAccumulator::check('override', 'Override header');
            self::fail('Expected RuntimeException from check()');
        } catch (RuntimeException $exception) {
            self::assertStringStartsWith('Override header', $exception->getMessage());
        }
    }

    public function testClearRemovesSingleAccumulator(): void
    {
        new ErrorAccumulator('remove-me');
        new ErrorAccumulator('keep-me');

        ErrorAccumulator::clear('remove-me');

        self::assertNull(ErrorAccumulator::get('remove-me'));
        self::assertNotNull(ErrorAccumulator::get('keep-me'));
    }

    public function testClearWildcardRemovesAll(): void
    {
        new ErrorAccumulator('one');
        new ErrorAccumulator('two');

        ErrorAccumulator::clear('*');

        self::assertNull(ErrorAccumulator::get('one'));
        self::assertNull(ErrorAccumulator::get('two'));
    }

    public function testClearedReferenceCanBeReused(): void
    {
        new ErrorAccumulator('recycled');

        ErrorAccumulator::clear('recycled');

        $fresh = new ErrorAccumulator('recycled');

        self::assertSame($fresh, ErrorAccumulator::get('recycled'));
    }
}

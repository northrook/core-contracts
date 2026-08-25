<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Invokable;
use Northrook\Contracts\Reference;
use Northrook\Contracts\Resettable;
use Northrook\InvalidArgumentException;
use Northrook\ReferenceTrait;
use Northrook\RuntimeException;
use PHPUnit\Framework\TestCase;

final class InterfacesTest extends TestCase
{
    // ── Interface shape ──────────────────────────────────────────

    public function testContractsAreInterfaces(): void
    {
        self::assertTrue(new \ReflectionClass(Invokable::class)->isInterface());
        self::assertTrue(new \ReflectionClass(Reference::class)->isInterface());
        self::assertTrue(new \ReflectionClass(Resettable::class)->isInterface());
    }

    public function testReferenceExtendsStringable(): void
    {
        self::assertContains(
            \Stringable::class,
            new \ReflectionClass(Reference::class)->getInterfaceNames(),
        );
    }

    public function testInvokableImplementationIsCallable(): void
    {
        $service = new class implements Invokable {
            public function __invoke(): string
            {
                return 'invoked';
            }
        };

        self::assertInstanceOf(Invokable::class, $service);
        self::assertSame('invoked', $service());
    }

    public function testResettableInstanceReset(): void
    {
        $service = new class implements Resettable {
            public int $resets = 0;

            public function reset(): void
            {
                $this->resets++;
            }
        };

        $service->reset();
        self::assertSame(1, $service->resets);
    }

    public function testResettableStaticReset(): void
    {
        $service = new class implements Resettable {
            public static int $resets = 0;

            public static function reset(): void
            {
                self::$resets++;
            }
        };

        $service::$resets = 0;
        $service::reset();
        self::assertSame(1, $service::$resets);
    }

    // ── ReferenceTrait ───────────────────────────────────────────

    public function testFromNormalizesValue(): void
    {
        $reference = InterfacesReferenceFixture::from('  Mixed Case  ');

        self::assertInstanceOf(InterfacesReferenceFixture::class, $reference);
        self::assertSame('mixed case', $reference->value);
    }

    public function testToStringReturnsValue(): void
    {
        $reference = InterfacesReferenceFixture::from('value');

        self::assertSame('value', (string) $reference);
    }

    public function testFromAcceptsStringable(): void
    {
        $reference = InterfacesReferenceFixture::from(
            InterfacesReferenceFixture::from('inner'),
        );

        self::assertSame('inner', $reference?->value);
    }

    public function testFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(InterfacesReferenceFixture::from('   '));
        self::assertNull(InterfacesReferenceFixture::from(''));
    }

    public function testFromReturnsNullForNonStringValue(): void
    {
        self::assertNull(InterfacesReferenceFixture::from(null));
        self::assertNull(InterfacesReferenceFixture::from(42));
        self::assertNull(InterfacesReferenceFixture::from(['value']));
    }

    public function testFromThrowWrapsConstructorFailureInRuntimeException(): void
    {
        try {
            InterfacesReferenceFixture::from('   ', throw: true);
            self::fail('Expected RuntimeException.');
        }
        catch (RuntimeException $exception) {
            self::assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
        }
    }

    public function testFromThrowRejectsNonStringValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InterfacesReferenceFixture::from(42, throw: true);
    }

    public function testIsValid(): void
    {
        self::assertTrue(InterfacesReferenceFixture::isValid('value'));
        self::assertTrue(InterfacesReferenceFixture::isValid('  padded  '));
        self::assertFalse(InterfacesReferenceFixture::isValid(''));
        self::assertFalse(InterfacesReferenceFixture::isValid('   '));
    }
}

final class InterfacesReferenceFixture implements Reference
{
    use ReferenceTrait;

    /**
     * @var non-empty-string
     */
    public readonly string $value;

    public function __construct(
        string|\Stringable $value,
    ) {
        $this->value = self::normalize($value);
    }

    public static function normalize(
        string|\Stringable $value,
    ): string {
        $normalized = \strtolower(\trim((string) $value));

        if ($normalized === '') {
            throw new InvalidArgumentException(
                context: [
                    'name'     => 'value',
                    'expected' => 'non-empty-string',
                    'received' => $value,
                ],
            );
        }

        return $normalized;
    }
}

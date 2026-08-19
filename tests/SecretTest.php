<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\Exportable;
use Northrook\Contracts\Tests\Support\SecretMask;
use Northrook\Export;
use Northrook\InvalidArgumentException;
use Northrook\Parameter\Secret;
use Northrook\Parameter\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for {@see Secret} as a secrecy-tier enum.
 */
final class SecretTest extends TestCase
{
    public function testCasesExist(): void
    {
        self::assertSame('SENSITIVE', Secret::SENSITIVE->name);
        self::assertSame('CREDENTIAL', Secret::CREDENTIAL->name);
        self::assertCount(2, Secret::cases());
    }

    public function testFromInstanceReturnsSame(): void
    {
        self::assertSame(Secret::SENSITIVE, Secret::from(Secret::SENSITIVE));
        self::assertSame(Secret::CREDENTIAL, Secret::from(Secret::CREDENTIAL));
    }

    /**
     * @param non-empty-string $input
     */
    #[DataProvider('provideValidTypesCaseInsensitive')]
    public function testFromStringNormalizesTypeCase(
        string $input,
        Secret $expected,
    ): void {
        self::assertSame($expected, Secret::from($input));
    }

    /**
     * @return \Generator<string, array{string, Secret}>
     */
    public static function provideValidTypesCaseInsensitive(): \Generator
    {
        yield 'sensitive lower' => ['sensitive', Secret::SENSITIVE];
        yield 'sensitive upper' => ['SENSITIVE', Secret::SENSITIVE];
        yield 'sensitive mixed' => ['SeNsItIvE', Secret::SENSITIVE];
        yield 'credential lower' => ['credential', Secret::CREDENTIAL];
        yield 'credential upper' => ['CREDENTIAL', Secret::CREDENTIAL];
    }

    public function testFromStringRejectsInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore-next-line Testing invalid input.
        Secret::from('nope');
    }

    public function testInvokeCredentialAlwaysOpaque(): void
    {
        self::assertSame('[secret::credential]', ( Secret::CREDENTIAL )('hunter2'));
        self::assertSame('[secret::credential]', ( Secret::CREDENTIAL )(123, ['x'], 'custom'));
    }

    public function testInvokeSensitiveUsesMaskOrType(): void
    {
        self::assertSame(SecretMask::sensitive('hunter2'), ( Secret::SENSITIVE )('hunter2'));
        self::assertSame('[secret::custom]', ( Secret::SENSITIVE )('hunter2', mask: 'custom'));
    }

    public function testInvokeSensitiveRespectsSensitiveParameterContext(): void
    {
        $mask = ( Secret::SENSITIVE )('x', [\SensitiveParameter::class]);

        self::assertSame('[secret::' . \SensitiveParameter::class . ']', $mask);
    }

    public function testExportIsEvalableViaExportHelper(): void
    {
        self::assertSame(Secret::SENSITIVE, eval('return ' . Export::value(Secret::SENSITIVE) . ';'));
        self::assertSame(Secret::CREDENTIAL, eval('return ' . Export::value(Secret::CREDENTIAL) . ';'));
        self::assertSame(Type::Setting, eval('return ' . Export::value(Type::Setting) . ';'));
        self::assertNotInstanceOf(Exportable::class, Secret::SENSITIVE);
        self::assertNotInstanceOf(Exportable::class, Type::Setting);
    }
}

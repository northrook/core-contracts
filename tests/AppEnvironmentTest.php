<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\AppEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppEnvironmentTest extends TestCase
{
    #[DataProvider('provideParseAliases')]
    public function testParseResolvesAliases(
        string $input,
        AppEnvironment $expected,
    ): void {
        self::assertSame($expected, AppEnvironment::parse($input));
    }

    /**
     * @return iterable<string, array{string, AppEnvironment}>
     */
    public static function provideParseAliases(): iterable
    {
        yield 'production' => ['production', AppEnvironment::Production];
        yield 'prod' => ['prod', AppEnvironment::Production];
        yield 'production uppercase' => ['PRODUCTION', AppEnvironment::Production];

        yield 'development' => ['development', AppEnvironment::Development];
        yield 'dev' => ['dev', AppEnvironment::Development];
        yield 'development mixed case' => ['DeVeLoPmEnT', AppEnvironment::Development];

        yield 'testing' => ['testing', AppEnvironment::Testing];
        yield 'test' => ['test', AppEnvironment::Testing];

        yield 'staging' => ['staging', AppEnvironment::Staging];
        yield 'staging uppercase' => ['STAGING', AppEnvironment::Staging];
    }

    #[DataProvider('provideUnknownValues')]
    public function testParseFallsBackToFailsafeForUnknownValues(
        string $input,
    ): void {
        self::assertSame(AppEnvironment::Failsafe, AppEnvironment::parse($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownValues(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'unknown' => ['local'];
        yield 'numeric' => ['123'];
    }

    public function testBackedValues(): void
    {
        self::assertSame('production', AppEnvironment::Production->value);
        self::assertSame('development', AppEnvironment::Development->value);
        self::assertSame('testing', AppEnvironment::Testing->value);
        self::assertSame('staging', AppEnvironment::Staging->value);
        self::assertSame('failsafe', AppEnvironment::Failsafe->value);
    }
}

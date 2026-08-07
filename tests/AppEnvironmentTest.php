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
        string         $input,
        AppEnvironment $expected,
    ): void {
        self::assertSame($expected, AppEnvironment::parse($input));
    }

    /**
     * @return \Generator<string, array{string, AppEnvironment}>
     */
    public static function provideParseAliases(): \Generator
    {
        yield 'production' => ['production', AppEnvironment::Production];
        yield 'prod alias' => ['prod', AppEnvironment::Production];
        yield 'production uppercase' => ['PRODUCTION', AppEnvironment::Production];
        yield 'prod mixed case' => ['Prod', AppEnvironment::Production];

        yield 'development' => ['development', AppEnvironment::Development];
        yield 'dev alias' => ['dev', AppEnvironment::Development];
        yield 'dev uppercase' => ['DEV', AppEnvironment::Development];

        yield 'testing' => ['testing', AppEnvironment::Testing];
        yield 'test alias' => ['test', AppEnvironment::Testing];
        yield 'testing uppercase' => ['TESTING', AppEnvironment::Testing];

        yield 'staging' => ['staging', AppEnvironment::Staging];
        yield 'staging uppercase' => ['STAGING', AppEnvironment::Staging];

        yield 'failsafe literal falls through to failsafe' => ['failsafe', AppEnvironment::Failsafe];
    }

    #[DataProvider('provideUnknownValues')]
    public function testParseFallsBackToFailsafeForUnknownValues(
        string $input,
    ): void {
        self::assertSame(AppEnvironment::Failsafe, AppEnvironment::parse($input));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideUnknownValues(): \Generator
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'unknown word' => ['local'];
        yield 'numeric' => ['123'];
        yield 'near-miss' => ['productions'];
        yield 'stage is not staging' => ['stage'];
    }

    #[DataProvider('provideBackedValues')]
    public function testBackedValues(
        AppEnvironment $case,
        string         $expected,
    ): void {
        self::assertSame($expected, $case->value);
    }

    /**
     * @return \Generator<string, array{AppEnvironment, string}>
     */
    public static function provideBackedValues(): \Generator
    {
        yield 'production' => [AppEnvironment::Production, 'production'];
        yield 'development' => [AppEnvironment::Development, 'development'];
        yield 'testing' => [AppEnvironment::Testing, 'testing'];
        yield 'staging' => [AppEnvironment::Staging, 'staging'];
        yield 'failsafe' => [AppEnvironment::Failsafe, 'failsafe'];
    }

    public function testParseRoundTripsEveryBackedValue(): void
    {
        foreach (AppEnvironment::cases() as $case) {
            self::assertSame($case, AppEnvironment::parse($case->value));
        }
    }
}

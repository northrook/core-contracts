<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Logger\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogLevel::class)]
final class LogLevelTest extends TestCase
{
    #[DataProvider('provideLibraryLabels')]
    public function testLabelReturnsLibraryAbbreviation(
        LogLevel $level,
        string   $expected,
    ): void {
        self::assertSame($expected, $level->label());
        self::assertSame(4, \strlen($level->label()));
    }

    #[DataProvider('provideMonologLabels')]
    public function testLabelReturnsMonologPrefix(
        LogLevel $level,
        string   $expected,
    ): void {
        self::assertSame($expected, $level->label(monolog: true));
        self::assertSame(4, \strlen($level->label(monolog: true)));
    }

    /**
     * @return \Generator<string, array{LogLevel, string}>
     */
    public static function provideLibraryLabels(): \Generator
    {
        yield 'debug' => [LogLevel::DEBUG, 'DBUG'];
        yield 'info' => [LogLevel::INFO, 'INFO'];
        yield 'notice' => [LogLevel::NOTICE, 'NOTE'];
        yield 'warning' => [LogLevel::WARNING, 'WARN'];
        yield 'error' => [LogLevel::ERROR, 'ERRO'];
        yield 'critical' => [LogLevel::CRITICAL, 'CRIT'];
        yield 'alert' => [LogLevel::ALERT, 'ALRT'];
        yield 'emergency' => [LogLevel::EMERGENCY, 'EMRG'];
    }

    /**
     * @return \Generator<string, array{LogLevel, string}>
     */
    public static function provideMonologLabels(): \Generator
    {
        yield 'debug' => [LogLevel::DEBUG, 'DEBU'];
        yield 'info' => [LogLevel::INFO, 'INFO'];
        yield 'notice' => [LogLevel::NOTICE, 'NOTI'];
        yield 'warning' => [LogLevel::WARNING, 'WARN'];
        yield 'error' => [LogLevel::ERROR, 'ERRO'];
        yield 'critical' => [LogLevel::CRITICAL, 'CRIT'];
        yield 'alert' => [LogLevel::ALERT, 'ALER'];
        yield 'emergency' => [LogLevel::EMERGENCY, 'EMER'];
    }
}

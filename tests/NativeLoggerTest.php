<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Logger\LogLevel;
use Northrook\Logger\NativeLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;

#[CoversClass(NativeLogger::class)]
final class NativeLoggerTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            if (\is_file($path)) {
                @\unlink($path);
            }
            elseif (\is_dir($path)) {
                @\rmdir($path);
            }
        }
    }

    public function testWritesInterpolatedMessageToErrorLog(): void
    {
        $output = $this->captureErrorLog(
            static fn(NativeLogger $logger) => $logger->info(
                'Generated {count} files.',
                ['count' => 3],
            ),
        );

        self::assertStringContainsString(
            '[info] Generated 3 files.',
            $output,
        );
    }

    public function testAcceptsLogLevelEnum(): void
    {
        $output = $this->captureErrorLog(
            static fn(NativeLogger $logger) => $logger->log(
                LogLevel::WARNING,
                'Enum level.',
            ),
        );

        self::assertStringContainsString(
            '[warning] Enum level.',
            $output,
        );
    }

    public function testThrowableMessageRetainsExceptionContext(): void
    {
        $output = $this->captureErrorLog(
            static fn(NativeLogger $logger) => $logger->error(
                new \RuntimeException('Something failed.'),
            ),
        );

        self::assertStringContainsString(
            '[error] Something failed.',
            $output,
        );
        self::assertStringContainsString(
            'RuntimeException: Something failed.',
            $output,
        );
    }

    public function testAppendsToExplicitLogFile(): void
    {
        $file = $this->tempPath('file.log');

        new NativeLogger($file)->warning('Persisted.');

        self::assertFileExists($file);
        self::assertStringContainsString(
            '[warning] Persisted.',
            (string) \file_get_contents($file),
        );
    }

    public function testCreatesDatedLogFileInDirectory(): void
    {
        $directory = $this->tempPath('logs');
        \mkdir($directory);
        $expected        = $directory . \DIRECTORY_SEPARATOR . \date('Y-m-d') . '.log';
        $this->cleanup[] = $expected;

        new NativeLogger($directory)->info('Directory target.');

        self::assertFileExists($expected);
        self::assertStringContainsString(
            '[info] Directory target.',
            (string) \file_get_contents($expected),
        );
    }

    public function testRejectsInvalidLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeLogger()->log('verbose', 'Not a PSR-3 level.');
    }

    /**
     * @param \Closure(NativeLogger): void $callback
     */
    private function captureErrorLog(
        \Closure $callback,
    ): string {
        $path     = $this->tempPath('error-log.log');
        $previous = \ini_get('error_log');

        \ini_set('error_log', $path);

        try {
            $callback(new NativeLogger);

            return (string) \file_get_contents($path);
        }
        finally {
            if (\is_string($previous)) {
                \ini_set('error_log', $previous);
            }
        }
    }

    private function tempPath(
        string $suffix,
    ): string {
        $path            = \sys_get_temp_dir() . '/nr-native-log-' . \bin2hex(\random_bytes(4)) . '-' . $suffix;
        $this->cleanup[] = $path;

        return $path;
    }
}

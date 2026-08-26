<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Context;
use Northrook\Context\AppEnv;
use Northrook\Logger\LogLevel;
use Northrook\Logger\NativeLogger;
use Northrook\Singleton;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;

#[CoversClass(NativeLogger::class)]
final class NativeLoggerTest extends TestCase
{
    /** @var list<string> */
    private array $cleanup = [];

    protected function setUp(): void
    {
        $this->resetContext();
        Context::register(appEnv: AppEnv::Development);
    }

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

        $this->resetContext();
    }

    private function resetContext(): void
    {
        $property = new \ReflectionProperty(Singleton::class, '_instance');
        $property->setValue(null, []);

        foreach (['_appEnv', '_appDebug', '_osFamily'] as $propertyName) {
            $property = new \ReflectionProperty(Context::class, $propertyName);
            $property->setValue(null, null);
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

        self::assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \d{3} \[INFO\] Generated 3 files\./',
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

        self::assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \d{3} \[WARN\] Enum level\./',
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

        self::assertMatchesRegularExpression(
            '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \d{3} \[ERRO\] Something failed\./',
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
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \d{3} \[WARN\] Persisted\.\n$/',
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
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \d{3} \[INFO\] Directory target\.\n$/',
            (string) \file_get_contents($expected),
        );
    }

    public function testLineUsesLibraryLabelAndTimestamp(): void
    {
        $file   = $this->tempPath('format.log');
        $before = \time();

        new NativeLogger($file)->critical('Uniform label.');

        $after = \time();
        $line  = \rtrim((string) \file_get_contents($file));

        self::assertMatchesRegularExpression(
            '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \d{3} \[CRIT\] Uniform label\.$/',
            $line,
            'Line must be `{Y-m-d H:i:s v} [{4-char label}] {message}`',
        );

        \preg_match(
            '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) (\d{3})/',
            $line,
            $matches,
        );

        $logged = \strtotime($matches[1]);
        self::assertNotFalse($logged);
        self::assertGreaterThanOrEqual($before, $logged);
        self::assertLessThanOrEqual($after, $logged);
    }

    #[DataProvider('provideLevelLabels')]
    public function testLogFileUsesExpectedLabel(
        string $method,
        string $label,
    ): void {
        $file = $this->tempPath("{$method}.log");

        new NativeLogger($file)->{$method}('Label check.');

        self::assertStringContainsString(
            "[{$label}] Label check.",
            (string) \file_get_contents($file),
        );
    }

    public function testRejectsInvalidLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NativeLogger()->log('verbose', 'Not a PSR-3 level.');
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function provideLevelLabels(): \Generator
    {
        yield 'debug' => ['debug', 'DBUG'];
        yield 'info' => ['info', 'INFO'];
        yield 'notice' => ['notice', 'NOTE'];
        yield 'warning' => ['warning', 'WARN'];
        yield 'error' => ['error', 'ERRO'];
        yield 'critical' => ['critical', 'CRIT'];
        yield 'alert' => ['alert', 'ALRT'];
        yield 'emergency' => ['emergency', 'EMRG'];
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

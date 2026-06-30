<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ErrorHandler\ErrorBuffer;
use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\ErrorHandler\RuntimeError;
use Northrook\Contracts\Exceptions\CurlException;
use Northrook\Contracts\Exceptions\ErrorException;
use Northrook\Contracts\Exceptions\FilesystemException;
use Northrook\Contracts\Exceptions\RuntimeException;
use Northrook\Contracts\Exceptions\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;

use const Northrook\Logger\LOG_LEVEL;

final class ErrorReportTest extends TestCase
{
    private ErrorBuffer $buffer;

    protected function setUp(): void
    {
        error_clear_last();
        ErrorBuffer::setShared($this->buffer = new ErrorBuffer());
    }

    protected function tearDown(): void
    {
        error_clear_last();
        ErrorBuffer::setShared(null);
    }

    public function testFromMergesExceptionContextIntoReportContext(): void
    {
        $exception = new ServiceNotFoundException(
            id: 'App\\Service',
            reference: 'default',
            alternatives: ['App\\Other'],
        );

        $report = ErrorReport::from($exception);

        self::assertSame('App\\Service', $report->context['id']);
        self::assertSame('default', $report->context['reference']);
        self::assertSame([
            'type'  => 'array',
            'value' => ['App\\Other'],
        ], $report->context['alternatives']);
        self::assertArrayHasKey('sapi', $report->context);
    }

    public function testFromCallerContextOverridesDefaultButNotExceptionContext(): void
    {
        $exception = new RuntimeException(
            message: 'failure',
            context: ['requestId' => 'abc-123'],
            previous: false,
        );

        $report = ErrorReport::from(
            $exception,
            context: ['requestId' => 'caller', 'route' => '/test'],
        );

        self::assertSame('abc-123', $report->context['requestId']);
        self::assertSame('/test', $report->context['route']);
        self::assertArrayNotHasKey('sapi', $report->context);
    }

    public function testFromResolvesPhpErrorAndPhpErrorsFromErrorException(): void
    {
        @\trigger_error('report me', E_USER_WARNING);

        $exception = new ErrorException();
        $report    = ErrorReport::from($exception);

        self::assertNotNull($report->phpError);
        self::assertSame(E_USER_WARNING, $report->phpError['type']);
        self::assertSame('report me', $report->phpError['message']);
        self::assertSame($exception->getFile(), $report->phpError['file']);
        self::assertSame($exception->getLine(), $report->phpError['line']);
        self::assertCount(1, $report->phpErrors);
        self::assertSame($report->phpError, $report->phpErrors[0]);
    }

    public function testFromBuildsMetaForCurlAndFilesystemExceptions(): void
    {
        $curlReport = ErrorReport::from(new CurlException('https://example.test'));
        $fileReport = ErrorReport::from(new FilesystemException(
            message: 'Denied',
            path: '/tmp/data.txt',
        ));

        self::assertSame(['url' => 'https://example.test'], $curlReport->meta);
        self::assertSame(['path' => '/tmp/data.txt'], $fileReport->meta);
    }

    public function testFromResolvesSeverityFromLogLevelCode(): void
    {
        $report = ErrorReport::from(new CurlException('https://example.test'));

        self::assertSame('error', $report->severity);
    }

    public function testFromResolvesCriticalSeverityForContractsRuntimeExceptionWithoutMappedCode(): void
    {
        $report = ErrorReport::from(new RuntimeException(
            message: 'bug',
            previous: false,
        ));

        self::assertSame('critical', $report->severity);
        self::assertSame(LOG_LEVEL['critical'], $report->code);
    }

    public function testPreviousReportIncludesExceptionContext(): void
    {
        $previous = new RuntimeException(
            message: 'root cause',
            context: ['token' => 'expired'],
            previous: false,
        );
        $exception = new RuntimeException(
            message: 'wrapper',
            previous: $previous,
        );

        $report = ErrorReport::from($exception);

        self::assertCount(1, $report->previous);
        self::assertSame('root cause', $report->previous[0]->message);
        self::assertSame('expired', $report->previous[0]->context['token']);
        self::assertArrayNotHasKey('sapi', $report->previous[0]->context);
    }

    public function testFromResolvesPhpErrorsFromSnapshottedBufferContext(): void
    {
        $this->buffer->recordFrom(E_USER_NOTICE, 'first', '/tmp/a.php', 1);
        $this->buffer->recordFrom(E_USER_WARNING, 'second', '/tmp/b.php', 2);

        $exception = new RuntimeException(message: 'wrapped');
        $report    = ErrorReport::from($exception);

        self::assertCount(2, $report->phpErrors);
        self::assertSame('first', $report->phpErrors[0]['message']);
        self::assertSame('second', $report->phpErrors[1]['message']);
        self::assertSame($report->phpErrors[0], $report->phpError);
    }

    public function testFromFallsBackToLiveBufferWhenExceptionHasNoSnapshot(): void
    {
        $this->buffer->recordFrom(E_USER_NOTICE, 'buffered', '/tmp/buffer.php', 4);

        $exception = new \Exception('plain exception');

        $report = ErrorReport::from($exception, buffer: $this->buffer);

        self::assertSame([[
            'type'    => E_USER_NOTICE,
            'message' => 'buffered',
            'file'    => '/tmp/buffer.php',
            'line'    => 4,
        ]], $report->phpErrors);
    }

    public function testFromResolvesRuntimeErrorInLegacyPhpErrorContext(): void
    {
        $runtimeError = RuntimeError::from([
            'type'    => E_USER_NOTICE,
            'message' => 'runtime error dto',
            'file'    => '/tmp/runtime.php',
            'line'    => 5,
        ]);

        $exception = new RuntimeException(
            message: 'wrapped',
            context: ['phpError' => $runtimeError],
            previous: false,
        );

        $report = ErrorReport::from($exception);

        self::assertSame($runtimeError->toArray(), $report->phpError);
        self::assertSame([$runtimeError->toArray()], $report->phpErrors);
    }

    public function testJsonSerializeIncludesPhpErrors(): void
    {
        @\trigger_error('json me', E_USER_NOTICE);

        $report = ErrorReport::from(new ErrorException());

        self::assertArrayHasKey('phpErrors', $report->jsonSerialize());
        self::assertNotSame([], $report->jsonSerialize()['phpErrors']);
    }
}

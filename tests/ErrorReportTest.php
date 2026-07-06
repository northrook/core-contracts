<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ErrorHandler\ErrorReport;
use Northrook\Contracts\ErrorHandler\ErrorSnapshot;
use Northrook\Contracts\ErrorHandler\RuntimeError;
use Northrook\Contracts\ErrorHandler\StackFrame;
use PHPUnit\Framework\TestCase;

final class ErrorReportTest extends TestCase
{
    public function testConstructsWithErrorSnapshotAndRuntimeError(): void
    {
        $error = ErrorSnapshot::from(
            class: \RuntimeException::class,
            message: 'failure',
            code: 1,
            file: '/tmp/example.php',
            line: 10,
            meta: ['url' => 'https://example.test'],
        );
        $phpError = RuntimeError::from([
            'type'    => E_USER_WARNING,
            'message' => 'report me',
            'file'    => '/tmp/warning.php',
            'line'    => 2,
        ]);

        $report = new ErrorReport(
            reference   : 'error-abc123',
            timestamp   : 1_700_000_000.5,
            severity    : 'error',
            error       : $error,
            stackFrames : [ StackFrame::from( [ 'file' => '/tmp/example.php', 'line' => 10, 'function' => 'main'])],
            context     : ['requestId' => 'abc-123'],
            phpError    : $phpError,
            phpErrors   : [$phpError],
        );

        self::assertSame('error-abc123', $report->reference);
        self::assertSame('failure', $report->error->message);
        self::assertSame(['url' => 'https://example.test'], $report->error->meta);
        self::assertSame('/tmp/example.php', $report->error->stackFrame->file);
        self::assertSame(10, $report->error->stackFrame->line);
        self::assertSame(E_USER_WARNING, $report->phpError->type);
        self::assertSame('report me', $report->phpErrors[0]->message);
        self::assertSame('abc-123', $report->context['requestId']);
    }

    public function testNestedPreviousReportsExposeErrorSnapshot(): void
    {
        $previous = new ErrorReport(
            reference   : 'error-previous',
            timestamp   : 1_700_000_000.0,
            severity    : 'critical',
            error       : ErrorSnapshot::from(
                class: \RuntimeException::class,
                message: 'root cause',
                code: 0,
                file: '/tmp/root.php',
                line: 1,
            ),
            stackFrames : [],
            context     : ['token' => 'expired'],
        );

        $report = new ErrorReport(
            reference   : 'error-wrapper',
            timestamp   : 1_700_000_001.0,
            severity    : 'critical',
            error       : ErrorSnapshot::from(
                class: \RuntimeException::class,
                message: 'wrapper',
                code: 0,
                file: '/tmp/wrapper.php',
                line: 5,
            ),
            stackFrames : [],
            previous    : [$previous],
        );

        self::assertCount(1, $report->previous);
        self::assertSame('root cause', $report->previous[0]->error->message);
        self::assertSame('expired', $report->previous[0]->context['token']);
    }

    public function testJsonSerializeUsesErrorKeyAndPhpErrors(): void
    {
        $phpError = RuntimeError::from([
            'type'    => E_USER_NOTICE,
            'message' => 'json me',
            'file'    => '/tmp/notice.php',
            'line'    => 3,
        ]);

        $report = new ErrorReport(
            reference   : 'error-json',
            timestamp   : 1_700_000_002.0,
            severity    : 'warning',
            error       : ErrorSnapshot::from(
                class: \ErrorException::class,
                message: 'notice',
                code: 0,
                file: '/tmp/notice.php',
                line: 3,
            ),
            stackFrames : [],
            phpError    : $phpError,
            phpErrors   : [$phpError],
        );

        $serialized = $report->jsonSerialize();

        self::assertArrayHasKey('error', $serialized);
        self::assertArrayNotHasKey('throwable', $serialized);
        self::assertInstanceOf(ErrorSnapshot::class, $serialized['error']);
        self::assertSame('notice', $serialized['error']->message);
        self::assertArrayHasKey('phpErrors', $serialized);
        self::assertCount(1, $serialized['phpErrors']);
        self::assertInstanceOf(RuntimeError::class, $serialized['phpErrors'][0]);
        self::assertSame('json me', $serialized['phpErrors'][0]->message);
    }

    public function testJsonStringRoundTripsThroughDataObject(): void
    {
        $report = new ErrorReport(
            reference   : 'error-string',
            timestamp   : 1_700_000_003.0,
            severity    : 'error',
            error       : ErrorSnapshot::from(
                class: \RuntimeException::class,
                message: 'encoded',
                code: 0,
                file: '/tmp/encoded.php',
                line: 7,
            ),
            stackFrames : [],
        );

        $json = $report->jsonString();
        $decoded = \json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertSame('error-string', $decoded['reference']);
        self::assertSame('encoded', $decoded['error']['message']);
        self::assertSame($json, (string) $report);
    }
}

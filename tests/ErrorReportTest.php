<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Context;
use Northrook\Context\AppEnv;
use Northrook\Contracts\Tests\Support\MixedArray;
use Northrook\Contracts\Tests\Support\RegistersTestContext;
use Northrook\ErrorHandler\ErrorReport;
use Northrook\ErrorHandler\ErrorSnapshot;
use Northrook\ErrorHandler\RuntimeError;
use Northrook\ErrorHandler\StackFrame;
use PHPUnit\Framework\TestCase;

final class ErrorReportTest extends TestCase
{
    use RegistersTestContext;

    protected function setUp(): void
    {
        $this->setUpTestContext();
    }

    protected function tearDown(): void
    {
        $this->tearDownTestContext();
    }

    public function testConstructorFieldsAreAccessible(): void
    {
        $snapshot = $this->snapshot();
        $frames   = [$this->stackFrame()];
        $phpError = $this->runtimeError('primary php error');

        $report = new ErrorReport(
            reference  : 'ref-123',
            timestamp  : 1_700_000_000.5,
            severity   : 'critical',
            error      : $snapshot,
            stackFrames: $frames,
            context    : ['request' => '/home'],
            dumps      : ['user' => 'bob'],
            phpError   : $phpError,
            phpErrors  : [$phpError],
        );

        self::assertSame('ref-123', $report->reference);
        self::assertSame(1_700_000_000.5, $report->timestamp);
        self::assertSame('critical', $report->severity);
        self::assertSame($snapshot, $report->error);
        self::assertSame($frames, $report->stackFrames);
        self::assertSame([], $report->previous);
        self::assertSame(['request' => '/home'], $report->context);
        self::assertSame(['user' => 'bob'], $report->dumps);
        self::assertSame($phpError, $report->phpError);
        self::assertSame([$phpError], $report->phpErrors);
        self::assertSame(Context::get()->appEnv, $report->environment);
    }

    public function testDefaultsAreEmpty(): void
    {
        $report = new ErrorReport(
            reference  : 'minimal',
            timestamp  : 1.0,
            severity   : 'error',
            error      : $this->snapshot(),
            stackFrames: [],
        );

        self::assertSame([], $report->previous);
        self::assertSame([], $report->context);
        self::assertSame([], $report->dumps);
        self::assertNull($report->phpError);
        self::assertSame([], $report->phpErrors);
    }

    public function testNestedPreviousReportsChain(): void
    {
        $inner = new ErrorReport(
            reference  : 'inner',
            timestamp  : 1.0,
            severity   : 'error',
            error      : $this->snapshot('InnerError'),
            stackFrames: [],
            context    : ['depth' => 'inner'],
        );

        $outer = new ErrorReport(
            reference  : 'outer',
            timestamp  : 2.0,
            severity   : 'critical',
            error      : $this->snapshot('OuterError'),
            stackFrames: [],
            previous   : [$inner],
        );

        self::assertCount(1, $outer->previous);
        self::assertSame($inner, $outer->previous[0]);
        self::assertSame('InnerError', $outer->previous[0]->error->class);
        self::assertSame(['depth' => 'inner'], $outer->previous[0]->context);
    }

    public function testJsonSerializeShape(): void
    {
        $report = new ErrorReport(
            reference  : 'shape',
            timestamp  : 1_700_000_000.5,
            severity   : 'warning',
            error      : $this->snapshot(),
            stackFrames: [$this->stackFrame()],
            phpErrors  : [$this->runtimeError('buffered')],
        );

        $serialized = $report->jsonSerialize();

        self::assertSame(
            [
                'environment',
                'reference',
                'timestamp',
                'severity',
                'error',
                'stackFrames',
                'previous',
                'context',
                'dumps',
                'phpError',
                'phpErrors',
            ],
            \array_keys($serialized),
        );
        self::assertSame('shape', $serialized['reference']);
        self::assertSame('warning', $serialized['severity']);
        self::assertSame('Testing', $serialized['environment']);
        self::assertInstanceOf(ErrorSnapshot::class, $serialized['error']);
        self::assertArrayHasKey('error', $serialized);
        self::assertArrayNotHasKey('throwable', $serialized);
    }

    public function testEnvironmentCapturedFromAppEnv(): void
    {
        $report = new ErrorReport(
            reference  : 'env',
            timestamp  : 1.0,
            severity   : 'error',
            error      : $this->snapshot(),
            stackFrames: [],
        );

        self::assertInstanceOf(AppEnv::class, $report->environment);
        self::assertSame(AppEnv::Testing, $report->environment);
    }

    public function testStringRoundTripsThroughJson(): void
    {
        $report = new ErrorReport(
            reference  : 'round-trip',
            timestamp  : 1_700_000_000.25,
            severity   : 'alert',
            error      : $this->snapshot('RoundTripError'),
            stackFrames: [],
            context    : ['key' => 'value'],
            phpErrors  : [$this->runtimeError('engine error')],
        );

        $decoded   = MixedArray::from(\json_decode((string) $report, true, 512, \JSON_THROW_ON_ERROR));
        $error     = MixedArray::at($decoded, 'error');
        $phpErrors = MixedArray::at($decoded, 'phpErrors');
        $firstPhp  = MixedArray::at($phpErrors, 0);

        self::assertSame('round-trip', $decoded['reference']);
        self::assertSame('alert', $decoded['severity']);
        self::assertSame('Testing', $decoded['environment']);
        self::assertSame('RoundTripError', $error['class']);
        self::assertSame(['key' => 'value'], $decoded['context']);
        self::assertSame('engine error', $firstPhp['message']);
    }

    private function snapshot(
        string $class = 'TestError',
    ): ErrorSnapshot {
        return ErrorSnapshot::from(
            class  : $class,
            message: 'snapshot message',
            code   : 500,
            file   : __FILE__,
            line   : __LINE__,
        );
    }

    private function stackFrame(): StackFrame
    {
        return new StackFrame(
            file    : __FILE__,
            line    : __LINE__,
            function: 'stackFrame',
            class   : self::class,
            type    : '->',
        );
    }

    private function runtimeError(
        string $message,
    ): RuntimeError {
        return RuntimeError::from([
            'type'    => \E_USER_WARNING,
            'message' => $message,
            'file'    => __FILE__,
            'line'    => __LINE__,
        ]);
    }
}

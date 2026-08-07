<?php

declare(strict_types=1);

namespace Northrook\Contracts\Tests;

use Northrook\Contracts\ContainerException;
use Northrook\Contracts\CurlException;
use Northrook\Contracts\DependencyException;
use Northrook\Contracts\ErrorBuffer;
use Northrook\Contracts\Exception\ErrorSnapshot;
use Northrook\Contracts\Exception\StackFrame;
use Northrook\Contracts\FileNotFoundException;
use Northrook\Contracts\FilesystemException;
use Northrook\Contracts\InvalidArgumentException;
use Northrook\Contracts\NotFoundException;
use Northrook\Contracts\RecursionException;
use Northrook\Contracts\RegexpException;
use Northrook\Contracts\RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use const Northrook\Contracts\LOG_LEVEL;

final class ExceptionsTest extends TestCase
{
    protected function setUp(): void
    {
        ErrorBuffer::shared()->reset();
        ErrorBuffer::setShared(null);
    }

    protected function tearDown(): void
    {
        ErrorBuffer::shared()->reset();
        ErrorBuffer::setShared(null);
    }

    #[DataProvider('provideExceptionSubclasses')]
    public function testSubclassExtendsContractsRuntimeException(
        \Closure $factory,
    ): void {
        $exception = $factory();

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public static function provideExceptionSubclasses(): \Generator
    {
        yield 'ContainerException' => [static fn() => new ContainerException('container failure')];
        yield 'CurlException' => [static fn() => new CurlException('https://example.com')];
        yield 'DependencyException' => [static fn() => new DependencyException('missing dependency')];
        yield 'FileNotFoundException' => [static fn() => new FileNotFoundException];
        yield 'FilesystemException' => [static fn() => new FilesystemException('fs failure')];
        yield 'InvalidArgumentException' => [static fn() => new InvalidArgumentException('invalid')];
        yield 'NotFoundException' => [static fn() => new NotFoundException('not found')];
        yield 'RecursionException' => [static fn() => new RecursionException];
        yield 'RegexpException' => [static fn() => new RegexpException('regexp failure')];
    }

    public function testContainerExceptionImplementsPsrInterface(): void
    {
        self::assertInstanceOf(ContainerExceptionInterface::class, new ContainerException('failure'));
    }

    public function testNotFoundExceptionImplementsPsrInterface(): void
    {
        $exception = new NotFoundException;

        self::assertInstanceOf(NotFoundExceptionInterface::class, $exception);
        self::assertSame('Unspecified error', $exception->getMessage());
        self::assertSame(LOG_LEVEL['critical'], $exception->getCode());
    }

    public function testDependencyExceptionRequiresMessage(): void
    {
        $exception = new DependencyException(
            'Package northrook/missing is required',
            context: ['package' => 'northrook/missing'],
        );

        self::assertSame('Package northrook/missing is required', $exception->getMessage());
        self::assertSame(LOG_LEVEL['critical'], $exception->getCode());
        self::assertSame('northrook/missing', $exception->context['package']);
    }

    public function testCurlExceptionCarriesUrlInPropertyAndContext(): void
    {
        $exception = new CurlException('https://example.com/api', context: ['attempt' => 2]);

        self::assertSame('https://example.com/api', $exception->url);
        self::assertSame('https://example.com/api', $exception->context['url']);
        self::assertSame(2, $exception->context['attempt']);
        self::assertSame("HTTP request to 'https://example.com/api' failed", $exception->getMessage());
        self::assertSame(LOG_LEVEL['error'], $exception->getCode());
    }

    public function testCurlExceptionAcceptsExplicitMessageAndCode(): void
    {
        $exception = new CurlException(
            'https://example.com',
            message: 'Connection refused',
            code: 42,
        );

        self::assertSame('Connection refused', $exception->getMessage());
        self::assertSame(42, $exception->getCode());
    }

    #[DataProvider('provideInvalidArgumentMessages')]
    public function testInvalidArgumentBuildsDefaultMessage(
        null|string $name,
        mixed       $expected,
        mixed       $received,
        string      $message,
    ): void {
        $exception = new InvalidArgumentException(
            name    : $name,
            expected: $expected,
            received: $received,
        );

        self::assertSame($message, $exception->getMessage());
    }

    public static function provideInvalidArgumentMessages(): \Generator
    {
        yield 'bare' => [null, null, null, 'Invalid argument'];
        yield 'name only' => ['config', null, null, "Invalid argument 'config'"];
        yield 'name and expected' => [
            'config',
            'string',
            null,
            "Invalid argument 'config' expected to be of type 'string'",
        ];
        yield 'descriptive expected label' => [
            'value',
            'string|Stringable',
            123,
            "Invalid argument 'value' expected to be of type 'string|Stringable', received 'int'",
        ];
        yield 'non-string expected uses debug type' => [
            'config',
            [],
            null,
            "Invalid argument 'config' expected to be of type 'array'",
        ];
        yield 'name, expected and received' => [
            'config',
            'string',
            42,
            "Invalid argument 'config' expected to be of type 'string', received 'int'",
        ];
        yield 'received only' => [null, null, 3.14, "Invalid argument, received 'float'"];
    }

    public function testInvalidArgumentRecordsNameExpectedReceivedInContext(): void
    {
        $exception = new InvalidArgumentException(
            name    : 'email',
            expected: 'string',
            received: null,
            context : ['extra' => 'kept'],
        );

        self::assertSame('email', $exception->context['name']);
        self::assertSame('string', $exception->context['expected']);
        self::assertArrayHasKey('received', $exception->context);
        self::assertSame('kept', $exception->context['extra']);
    }

    public function testInvalidArgumentHonorsExplicitMessage(): void
    {
        $exception = new InvalidArgumentException(
            message: 'Explicit message',
            name   : 'ignored',
        );

        self::assertSame('Explicit message', $exception->getMessage());
    }

    #[DataProvider('provideRegexpErrorMappings')]
    public function testRegexpExceptionMapsPregErrorCodes(
        int    $pregError,
        string $message,
    ): void {
        $exception = new RegexpException($pregError);

        self::assertSame($message, $exception->getMessage());
        self::assertSame($pregError, $exception->getCode());
    }

    public static function provideRegexpErrorMappings(): \Generator
    {
        yield 'internal' => [\PREG_INTERNAL_ERROR, 'Unspecified Internal error'];
        yield 'backtrack limit' => [\PREG_BACKTRACK_LIMIT_ERROR, 'Backtrack: limit was exhausted'];
        yield 'recursion limit' => [\PREG_RECURSION_LIMIT_ERROR, 'Recursion: limit was exhausted'];
        yield 'bad utf8' => [\PREG_BAD_UTF8_ERROR, 'UTF-8: Malformed data'];
        yield 'bad utf8 offset' => [\PREG_BAD_UTF8_OFFSET_ERROR, 'UTF-8: Invalid offset'];
        yield 'jit stack limit' => [\PREG_JIT_STACKLIMIT_ERROR, 'JIT: Insufficient compiler disk space'];
    }

    public function testRegexpExceptionUnknownCodeFallsBack(): void
    {
        $exception = new RegexpException(999);

        self::assertSame('Unspecified error - invalid flag or message provided.', $exception->getMessage());
        self::assertSame(999, $exception->getCode());
    }

    public function testRegexpExceptionStringMessageDefaultsCode(): void
    {
        $exception = new RegexpException('custom regexp failure');

        self::assertSame('custom regexp failure', $exception->getMessage());
        self::assertSame(LOG_LEVEL['error'], $exception->getCode());
    }

    public function testRegexpCheckThrowsOnPregError(): void
    {
        @\preg_match('//u', "\xB1\x31");

        self::assertSame(\PREG_BAD_UTF8_ERROR, \preg_last_error());

        try {
            RegexpException::check();
            self::fail('Expected RegexpException from check()');
        } catch (RegexpException $exception) {
            self::assertSame('UTF-8: Malformed data', $exception->getMessage());
            self::assertSame(\PREG_BAD_UTF8_ERROR, $exception->getCode());
        }
    }

    public function testRegexpCheckPassesWhenNoError(): void
    {
        \preg_match('//', 'clean');

        self::assertSame(\PREG_NO_ERROR, \preg_last_error());

        RegexpException::check();

        self::assertSame(\PREG_NO_ERROR, \preg_last_error());
    }

    public function testFilesystemExceptionExposesPath(): void
    {
        $exception = new FilesystemException('write failed', path: '/var/data/file.txt');

        self::assertSame('/var/data/file.txt', $exception->getPath());
        self::assertSame('/var/data/file.txt', $exception->context['path']);
        self::assertSame(LOG_LEVEL['error'], $exception->getCode());
    }

    public function testFilesystemExceptionPathIsNullWhenOmitted(): void
    {
        $exception = new FilesystemException('generic failure');

        self::assertNull($exception->getPath());
        self::assertSame([], $exception->context);
    }

    public function testFileNotFoundBuildsMessageFromPath(): void
    {
        $exception = new FileNotFoundException(path: '/missing/file.php');

        self::assertSame("File '/missing/file.php' could not be found.", $exception->getMessage());
        self::assertSame('/missing/file.php', $exception->getPath());
    }

    #[DataProvider('provideFileNotFoundEmptyPaths')]
    public function testFileNotFoundGenericMessageWithoutPath(
        null|string $path,
    ): void {
        $exception = new FileNotFoundException(path: $path);

        self::assertSame('File could not be found.', $exception->getMessage());
    }

    public static function provideFileNotFoundEmptyPaths(): \Generator
    {
        yield 'null path' => [null];
        yield 'empty path' => [''];
    }

    public function testFileNotFoundExtendsFilesystemException(): void
    {
        self::assertInstanceOf(FilesystemException::class, new FileNotFoundException);
    }

    public function testRecursionExceptionDefaults(): void
    {
        $exception = new RecursionException;

        self::assertSame('Recursion limit exceeded.', $exception->getMessage());
        self::assertSame(LOG_LEVEL['critical'], $exception->getCode());
        self::assertSame([], $exception->context);
        self::assertNull($exception->getPrevious());
    }

    public function testStackFrameFromArray(): void
    {
        // @phpstan-ignore-next-line Fictional stack frame.
        $frame = StackFrame::from([
            'file'     => '/src/app.php',
            'line'     => 10,
            'function' => 'run',
            'class'    => 'App\\Runner',
            'type'     => '->',
            'args'     => ['a' => 1],
        ]);

        self::assertSame('/src/app.php', $frame->file);
        self::assertSame(10, $frame->line);
        self::assertSame('run', $frame->function);
        self::assertSame('App\\Runner', $frame->class);
        self::assertSame('->', $frame->type);
        self::assertSame(['a' => 1], $frame->args);
        self::assertSame([], $frame->code);
    }

    public function testStackFrameFromEmptyArrayDefaultsToNull(): void
    {
        $frame = StackFrame::from([]);

        self::assertNull($frame->file);
        self::assertNull($frame->line);
        self::assertNull($frame->function);
        self::assertNull($frame->class);
        self::assertNull($frame->type);
        self::assertSame([], $frame->args);
        self::assertSame([], $frame->code);
    }

    public function testStackFrameFromThrowable(): void
    {
        try {
            $this->throwForStackFrame();
        } catch (\RuntimeException $exception) {
            $frame = StackFrame::from($exception);
        }

        self::assertSame(__FILE__, $frame->file);
        self::assertSame('throwForStackFrame', $frame->function);
        self::assertSame(self::class, $frame->class);
        self::assertSame('->', $frame->type);
        self::assertIsInt($frame->line);
    }

    public function testStackFrameReadsCodeSnippetForReadableFile(): void
    {
        $frame = StackFrame::from(['file' => __FILE__, 'line' => $line = __LINE__ + 1], codeRadius: 2);
        self::assertArrayHasKey($line, $frame->code);
        self::assertSame('self::assertArrayHasKey($line, $frame->code);', \trim($frame->code[$line]));
        self::assertNotSame([], $frame->code);
    }

    public function testErrorSnapshotCarriesFieldsAndStackFrame(): void
    {
        $snapshot = ErrorSnapshot::from(
            class  : 'App\\Failure',
            message: 'it broke',
            code   : 500,
            file   : '/src/app.php',
            line   : 33,
            meta   : ['hint' => 'check config'],
        );

        self::assertSame('App\\Failure', $snapshot->class);
        self::assertSame('it broke', $snapshot->message);
        self::assertSame(500, $snapshot->code);
        self::assertSame('/src/app.php', $snapshot->file);
        self::assertSame(33, $snapshot->line);
        self::assertSame(['hint' => 'check config'], $snapshot->meta);
        self::assertSame('/src/app.php', $snapshot->stackFrame->file);
        self::assertSame(33, $snapshot->stackFrame->line);
        self::assertSame('App\\Failure', $snapshot->stackFrame->class);
        self::assertNull($snapshot->stackFrame->function);
    }

    private function throwForStackFrame(): never
    {
        throw new \RuntimeException('stack frame probe');
    }
}
